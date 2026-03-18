<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Spotify;

use App\Module\Spotify\Application\Port\OAuthStateManagerInterface;
use App\Module\Spotify\Domain\Exception\SpotifyOAuthStateException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class SpotifyOAuthStateManager implements OAuthStateManagerInterface
{
    private const CACHE_PREFIX = 'spotify_oauth_state_';
    private const TTL_SECONDS = 600;

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function createState(string $profileId): string
    {
        $state = bin2hex(random_bytes(16));
        $key = self::CACHE_PREFIX . $state;
        $this->cache->get($key, function (ItemInterface $item) use ($profileId): string {
            $item->expiresAfter(self::TTL_SECONDS);
            return $profileId;
        });
        return $state;
    }

    public function consumeState(string $state): string
    {
        if ($state === '') {
            throw new SpotifyOAuthStateException('Missing state parameter.');
        }
        $key = self::CACHE_PREFIX . $state;
        $profileId = $this->cache->get($key, function (ItemInterface $item): never {
            $item->expiresAfter(0);
            throw new SpotifyOAuthStateException('State expired or already used.');
        });
        $this->cache->delete($key);
        return $profileId;
    }
}
