<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\Port\MediaEngineInterface;
use App\Module\AudioExtractor\Domain\ExtractorBusyException;
use Symfony\Component\Lock\LockFactory;

/**
 * Self-updates the extraction engine (`yt-dlp -U`) under the SAME exclusive lock as
 * {@see ExtractAudio}. This is the whole point of the lock: replacing the yt-dlp binary
 * while an extraction is in flight would corrupt that running job. If an extraction
 * holds the lock, the update fails fast with {@see ExtractorBusyException} (409).
 */
final readonly class UpdateEngine
{
    public function __construct(
        private MediaEngineInterface $engine,
        private LockFactory $lockFactory,
        private int $lockTtlSeconds = 3600,
    ) {
    }

    public function __invoke(): string
    {
        $lock = $this->lockFactory->createLock(ExtractAudio::ENGINE_LOCK_KEY, (float) $this->lockTtlSeconds);
        if (!$lock->acquire()) {
            throw new ExtractorBusyException(
                'An extraction is currently running. The engine can only be updated when idle.',
            );
        }

        try {
            return $this->engine->update();
        } finally {
            $lock->release();
        }
    }
}
