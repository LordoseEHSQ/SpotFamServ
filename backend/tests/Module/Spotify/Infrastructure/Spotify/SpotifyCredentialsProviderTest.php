<?php

declare(strict_types=1);

namespace App\Tests\Module\Spotify\Infrastructure\Spotify;

use App\Module\Spotify\Application\Port\SpotifyAppConfigRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyAppConfiguration;
use App\Module\Spotify\Infrastructure\Spotify\SpotifyCredentialsProvider;
use PHPUnit\Framework\TestCase;

class SpotifyCredentialsProviderTest extends TestCase
{
    public function test_uses_db_config_when_complete(): void
    {
        $config = new SpotifyAppConfiguration();
        $config->setSpotifyClientId('db-client');
        $config->setSpotifyClientSecret('db-secret');
        $config->setRedirectUri('http://127.0.0.1:8080/api/v1/spotify/callback');

        $repo = $this->createMock(SpotifyAppConfigRepositoryInterface::class);
        $repo->method('findActive')->willReturn($config);

        $provider = new SpotifyCredentialsProvider($repo, 'env-client', 'env-secret', 'http://env/callback');
        $creds = $provider->current();

        $this->assertSame('db', $creds->source);
        $this->assertSame('db-client', $creds->clientId);
        $this->assertSame('db-secret', $creds->clientSecret);
        $this->assertSame('http://127.0.0.1:8080/api/v1/spotify/callback', $creds->redirectUri);
        $this->assertSame(SpotifyCredentialsProvider::DEFAULT_SCOPES, $creds->scopes);
    }

    public function test_falls_back_to_env_when_no_db_config(): void
    {
        $repo = $this->createMock(SpotifyAppConfigRepositoryInterface::class);
        $repo->method('findActive')->willReturn(null);

        $provider = new SpotifyCredentialsProvider($repo, 'env-client', 'env-secret', 'http://env/callback');
        $creds = $provider->current();

        $this->assertSame('env', $creds->source);
        $this->assertSame('env-client', $creds->clientId);
        $this->assertSame('env-secret', $creds->clientSecret);
        $this->assertSame('http://env/callback', $creds->redirectUri);
    }

    public function test_falls_back_to_env_when_db_config_incomplete(): void
    {
        // Nur Client ID gesetzt, Secret + Redirect fehlen -> nicht vollständig -> env gewinnt ganzheitlich.
        $config = new SpotifyAppConfiguration();
        $config->setSpotifyClientId('db-client-only');

        $repo = $this->createMock(SpotifyAppConfigRepositoryInterface::class);
        $repo->method('findActive')->willReturn($config);

        $provider = new SpotifyCredentialsProvider($repo, 'env-client', 'env-secret', 'http://env/callback');
        $creds = $provider->current();

        $this->assertSame('env', $creds->source);
        $this->assertSame('env-client', $creds->clientId);
        $this->assertSame('env-secret', $creds->clientSecret);
    }
}
