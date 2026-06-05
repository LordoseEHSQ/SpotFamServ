<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application\MessageHandler;

use App\Module\AudioExtractor\Application\ExtractAudio;
use App\Module\AudioExtractor\Application\Message\ExtractAudioMessage;
use App\Module\AudioExtractor\Application\Port\AudioJobRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs the actual extraction for a queued job in the background worker (D-032/D-033).
 *
 * Failure policy: yt-dlp errors are mostly deterministic, so Messenger retries are off
 * (max_retries: 0). Every failure is caught and recorded as AudioJob.status=failed – the
 * single user-facing source of truth – and the message is acked (not re-thrown), so it does
 * not bounce to the failure transport. Only truly unexpected infrastructure errors (e.g. the
 * job vanished) are logged.
 */
#[AsMessageHandler]
final readonly class ExtractAudioMessageHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private AudioJobRepositoryInterface $jobs,
        private ExtractAudio $extractAudio,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(ExtractAudioMessage $message): void
    {
        $job = $this->jobs->findById($message->jobId);
        if ($job === null) {
            $this->logger->warning('AudioJob {id} not found; dropping message.', ['id' => $message->jobId]);

            return;
        }

        // A job can only be processed once. If it was canceled (or already handled), skip.
        if (!$job->isPending()) {
            return;
        }

        $job->markRunning();
        $this->jobs->save($job);

        // Log the host only, not the full URL (avoid logging long/query-laden source URLs).
        $host = parse_url($job->getUrl(), \PHP_URL_HOST) ?: 'unknown';
        $startedAt = microtime(true);
        $this->logger->info('AudioJob {id} started ({format} from {host}).', [
            'id' => $message->jobId,
            'format' => $job->getFormat(),
            'host' => $host,
        ]);

        try {
            $stored = ($this->extractAudio)($job->getUrl(), $job->getFormat(), $job->getBitrateKbps());
            $job->markDone($stored->name);
            $this->logger->info('AudioJob {id} done in {ms}ms ({file}).', [
                'id' => $message->jobId,
                'ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'file' => $stored->name,
            ]);
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());
            $this->logger->warning('AudioJob {id} failed after {ms}ms: {error}', [
                'id' => $message->jobId,
                'ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage(),
            ]);
        }

        $this->jobs->save($job);
    }
}
