<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Session;

use App\Module\Scan\Application\Port\PlaybackSessionStoreInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Ephemeral playback-session store backed by the application cache (cache.app).
 * Survives across requests but expires after TTL — acceptable for next/previous,
 * which only matters while the family is actively listening.
 */
final class CachePlaybackSessionStore implements PlaybackSessionStoreInterface
{
    private const PREFIX = 'playback_session_';
    private const GLOBAL_KEY = self::PREFIX . 'global';
    private const TTL_SECONDS = 21600; // 6 h

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function remember(string $profileId, string $readerId = ''): void
    {
        $this->store(self::GLOBAL_KEY, $profileId);
        if ($readerId !== '') {
            $this->store($this->readerKey($readerId), $profileId);
        }
    }

    public function currentProfileId(string $readerId = ''): ?string
    {
        if ($readerId !== '') {
            $perReader = $this->read($this->readerKey($readerId));
            if ($perReader !== null) {
                return $perReader;
            }
        }
        return $this->read(self::GLOBAL_KEY);
    }

    private function readerKey(string $readerId): string
    {
        return self::PREFIX . 'reader_' . sha1($readerId);
    }

    private function store(string $key, string $value): void
    {
        $item = $this->cache->getItem($key);
        $item->set($value);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);
    }

    private function read(string $key): ?string
    {
        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            return null;
        }
        $value = $item->get();
        return is_string($value) && $value !== '' ? $value : null;
    }
}
