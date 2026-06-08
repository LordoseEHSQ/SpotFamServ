<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Http;

use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Domain\FlashArtifact;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\System\Application\Port\SystemConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/readers/firmware', name: 'api_reader_firmware_', format: 'json')]
final class ReaderFirmwareController
{
    private const SUPPORTED_BOARD = 'esp32-wroom-32';

    public function __construct(
        private readonly SystemConfigurationProviderInterface $systemConfig,
        private readonly FlashArtifactRepositoryInterface $artifacts,
        private readonly ReaderDeviceRepositoryInterface $readerDevices,
        private readonly string $firmwareDir,
    ) {
    }

    #[Route(path: '/manifest', name: 'manifest', methods: ['GET'])]
    public function manifest(Request $request): Response
    {
        // Aktiver OTA-Kanal aus der System-Konfiguration (DB, sonst Default `stable`, D-029/B4).
        $supportedChannel = $this->systemConfig->getOtaChannel();

        $board = strtolower(trim($request->query->getString('board')));
        $channel = strtolower(trim($request->query->getString('channel', $supportedChannel)));
        $currentVersion = trim($request->query->getString('current_version'));

        $readerId = trim($request->query->getString('reader_id'));

        if ($board === '' || $currentVersion === '') {
            return $this->error('invalid_request', 'board and current_version are required.', Response::HTTP_BAD_REQUEST);
        }

        if ($board !== self::SUPPORTED_BOARD || $channel !== strtolower($supportedChannel)) {
            return $this->error('unsupported_board', 'Board or firmware channel is not supported.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (preg_match('/^\d+\.\d+\.\d+$/', $currentVersion) !== 1) {
            return $this->error('invalid_request', 'current_version must use MAJOR.MINOR.PATCH.', Response::HTTP_BAD_REQUEST);
        }

        // Use manifest check as a heartbeat: update last_seen_at and record installed firmware_version.
        if ($readerId !== '') {
            $reader = $this->readerDevices->findByReaderId($readerId);
            if ($reader !== null) {
                $reader->touchSeen(
                    $request->getClientIp(),
                    $currentVersion,
                    $board,
                    $channel,
                );
                $this->readerDevices->save($reader);
            }
        }

        $artifact = $this->findUpdateArtifact($board, $channel, $currentVersion);
        if ($artifact === null) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse([
            'board' => $artifact->getBoard(),
            'channel' => $artifact->getChannel(),
            'version' => $artifact->getVersion(),
            'min_version' => $currentVersion,
            'download_url' => sprintf(
                '/api/v1/readers/firmware/%s/%s/%s.bin',
                rawurlencode($artifact->getBoard()),
                rawurlencode($artifact->getChannel()),
                rawurlencode($artifact->getVersion()),
            ),
            'sha256' => $artifact->getSha256(),
            'size_bytes' => $artifact->getSizeBytes(),
            'signature' => null,
            'released_at' => $artifact->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route(path: '/{board}/{channel}/{version}.bin', name: 'download', methods: ['GET'])]
    public function download(string $board, string $channel, string $version): Response
    {
        $board = strtolower(trim($board));
        $channel = strtolower(trim($channel));
        $version = trim($version);

        if ($board !== self::SUPPORTED_BOARD || preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            return $this->error('invalid_request', 'Invalid board or version.', Response::HTTP_BAD_REQUEST);
        }

        $artifact = $this->artifacts->findByBoardChannelVersion($board, $channel, $version);
        if ($artifact === null) {
            return $this->error('not_found', 'Firmware artifact not found.', Response::HTTP_NOT_FOUND);
        }

        $filename = $artifact->getFilename();
        if ($filename !== basename($filename) || str_contains($filename, '..') || str_contains($filename, "\0")) {
            return $this->error('invalid_artifact', 'Invalid firmware artifact filename.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $baseDir = realpath(rtrim($this->firmwareDir, '/'));
        if ($baseDir === false) {
            return $this->error('not_found', 'Firmware directory not found.', Response::HTTP_NOT_FOUND);
        }

        $path = realpath($baseDir . '/' . $filename);
        if ($path === false || dirname($path) !== $baseDir || !is_file($path)) {
            return $this->error('not_found', 'Firmware file not found.', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('X-Firmware-Sha256', $artifact->getSha256());
        return $response;
    }

    private function findUpdateArtifact(string $board, string $channel, string $currentVersion): ?FlashArtifact
    {
        $candidates = array_filter(
            $this->artifacts->findByBoardChannel($board, $channel),
            static fn (FlashArtifact $artifact): bool => version_compare($artifact->getVersion(), $currentVersion, '>'),
        );

        usort(
            $candidates,
            static fn (FlashArtifact $a, FlashArtifact $b): int => version_compare($b->getVersion(), $a->getVersion()),
        );

        return $candidates[0] ?? null;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}
