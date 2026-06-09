<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Infrastructure\Http;

use App\Module\AudioExtractor\Application\CancelAudioJob;
use App\Module\AudioExtractor\Application\CreateAudioJob;
use App\Module\AudioExtractor\Application\DismissAudioJob;
use App\Module\AudioExtractor\Application\ExtractAudio;
use App\Module\AudioExtractor\Application\Port\AudioJobRepositoryInterface;
use App\Module\AudioExtractor\Application\Port\AudioStorageInterface;
use App\Module\AudioExtractor\Application\Port\MediaEngineInterface;
use App\Module\AudioExtractor\Application\UpdateEngine;
use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\AudioJob;
use App\Module\AudioExtractor\Domain\ExtractorBusyException;
use App\Module\AudioExtractor\Domain\StoredAudioFile;
use App\Shared\Application\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Audio-Extractor endpoints (normal feature, always on):
 *  - GET    /config              formats, bitrates, limits, current engine version.
 *  - POST   /extract             enqueue an extraction job (async) → 202 + job_id.
 *  - GET    /jobs                list recent jobs.
 *  - GET    /jobs/{id}           single job status/result/error.
 *  - DELETE /jobs/{id}           cancel a still-pending job.
 *  - GET    /files               list stored files + total size.
 *  - GET    /files/{name}        download a stored file.
 *  - DELETE /files/{name}        delete a stored file.
 *  - POST   /update              self-update the yt-dlp engine.
 *
 * Intended for legal sources only; the user is responsible for each URL.
 */
#[Route(path: '/audio-extractor', name: 'api_audio_extractor_', format: 'json')]
final class AudioExtractorController
{
    public function __construct(
        private readonly CreateAudioJob $createAudioJob,
        private readonly CancelAudioJob $cancelAudioJob,
        private readonly DismissAudioJob $dismissAudioJob,
        private readonly AudioJobRepositoryInterface $jobs,
        private readonly AudioStorageInterface $storage,
        private readonly MediaEngineInterface $engine,
        private readonly UpdateEngine $updateEngine,
        private readonly int $maxDurationSeconds = 1800,
    ) {
    }

    #[Route(path: '/config', name: 'config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        return new JsonResponse([
            'formats' => array_map(static fn (AudioFormat $f): array => [
                'value' => $f->value,
                'supports_bitrate' => $f->supportsBitrate(),
            ], AudioFormat::cases()),
            'bitrates_kbps' => ExtractAudio::ALLOWED_BITRATES_KBPS,
            'default_bitrate_kbps' => ExtractAudio::DEFAULT_BITRATE_KBPS,
            'max_duration_seconds' => $this->maxDurationSeconds,
            'engine_version' => $this->engine->version(),
        ]);
    }

    /**
     * Enqueue an extraction. The work runs in the background worker, so this returns
     * immediately with 202 + job_id (changed from the old synchronous 201, D-032). The
     * client polls GET /jobs/{id} for progress and the result file.
     */
    #[Route(path: '/extract', name: 'extract', methods: ['POST'])]
    public function extract(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = $request->getContent() === '' ? [] : $request->toArray();

        $url = isset($body['url']) ? (string) $body['url'] : '';
        $format = isset($body['format']) ? (string) $body['format'] : AudioFormat::Mp3->value;
        $bitrate = isset($body['bitrate_kbps']) && is_numeric($body['bitrate_kbps'])
            ? (int) $body['bitrate_kbps']
            : null;

        $job = ($this->createAudioJob)($url, $format, $bitrate);

        return new JsonResponse($this->jobToArray($job), Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/jobs', name: 'jobs_list', methods: ['GET'])]
    public function listJobs(): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map($this->jobToArray(...), $this->jobs->recent()),
        ]);
    }

    #[Route(path: '/jobs/{id}', name: 'jobs_get', methods: ['GET'])]
    public function getJob(string $id): JsonResponse
    {
        $job = $this->jobs->findById($id);
        if ($job === null) {
            throw new NotFoundException('Job not found.');
        }

        return new JsonResponse($this->jobToArray($job));
    }

    #[Route(path: '/jobs/{id}', name: 'jobs_cancel', methods: ['DELETE'])]
    public function cancelJob(string $id): JsonResponse
    {
        try {
            $job = $this->jobs->findById($id);
            if ($job === null) {
                return new JsonResponse(['error' => 'Job not found.'], Response::HTTP_NOT_FOUND);
            }

            if (in_array($job->getStatus(), [AudioJob::STATUS_FAILED, AudioJob::STATUS_CANCELED], true)) {
                ($this->dismissAudioJob)($id);
                return new JsonResponse(null, Response::HTTP_NO_CONTENT);
            }

            return new JsonResponse($this->jobToArray(($this->cancelAudioJob)($id)));
        } catch (NotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (ExtractorBusyException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    #[Route(path: '/files', name: 'files_list', methods: ['GET'])]
    public function listFiles(): JsonResponse
    {
        $files = $this->storage->list();

        return new JsonResponse([
            'items' => array_map($this->fileToArray(...), $files),
            'total_size_bytes' => $this->storage->totalSizeBytes(),
        ]);
    }

    #[Route(path: '/files/{name}', name: 'files_download', methods: ['GET'], requirements: ['name' => '[^/]+'])]
    public function downloadFile(string $name): BinaryFileResponse
    {
        $path = $this->storage->absolutePath($name);
        if ($path === null) {
            throw new NotFoundException('File not found.');
        }

        $response = new BinaryFileResponse($path);
        // Set Content-Type explicitly: BinaryFileResponse otherwise tries to guess via the
        // symfony/mime component, which is not installed (would throw a 500 on prepare()).
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $response->headers->set('Content-Type', AudioFormat::tryFrom($extension)?->mimeType() ?? 'application/octet-stream');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($path),
            $this->asciiFallback(basename($path)),
        );

        return $response;
    }

    #[Route(path: '/files/{name}', name: 'files_delete', methods: ['DELETE'], requirements: ['name' => '[^/]+'])]
    public function deleteFile(string $name): JsonResponse
    {
        if (!$this->storage->delete($name)) {
            throw new NotFoundException('File not found.');
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/update', name: 'update', methods: ['POST'])]
    public function update(): JsonResponse
    {
        set_time_limit(180);
        $version = ($this->updateEngine)();

        return new JsonResponse(['engine_version' => $version]);
    }

    /**
     * @return array{id: string, url: string, format: string, bitrate_kbps: int|null, status: string, progress: int, error: string|null, result_file: string|null, download_url: string|null, created_at: string, updated_at: string}
     */
    private function jobToArray(AudioJob $job): array
    {
        $resultFile = $job->getResultFile();

        return [
            'id' => $job->getId(),
            'url' => $job->getUrl(),
            'format' => $job->getFormat(),
            'bitrate_kbps' => $job->getBitrateKbps(),
            'status' => $job->getStatus(),
            'progress' => $job->getProgress(),
            'error' => $job->getError(),
            'result_file' => $resultFile,
            'download_url' => $resultFile !== null
                ? '/api/v1/audio-extractor/files/' . rawurlencode($resultFile)
                : null,
            'created_at' => $job->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $job->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{name: string, size_bytes: int, created_at: string, mime_type: string, download_url: string}
     */
    private function fileToArray(StoredAudioFile $file): array
    {
        return [
            'name' => $file->name,
            'size_bytes' => $file->sizeBytes,
            'created_at' => $file->createdAt->format(\DateTimeInterface::ATOM),
            'mime_type' => $file->mimeType,
            'download_url' => '/api/v1/audio-extractor/files/' . rawurlencode($file->name),
        ];
    }

    private function asciiFallback(string $name): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'audio';

        return $ascii !== '' ? $ascii : 'audio';
    }
}
