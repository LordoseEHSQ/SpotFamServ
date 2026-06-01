<?php

declare(strict_types=1);

namespace App\Tests\Module\Spotify\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\Spotify\Application\Dto\SpotifyCredentials;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyAppConfigRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyCredentialsProviderInterface;
use App\Module\Spotify\Application\ValidateSpotifyAppConfig;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;
use PHPUnit\Framework\TestCase;

class ValidateSpotifyAppConfigTest extends TestCase
{
    public function test_valid_when_spotify_accepts_credentials(): void
    {
        $apiClient = $this->createMock(SpotifyApiClientInterface::class);
        $apiClient->expects($this->once())
            ->method('checkClientCredentials')
            ->with('cid', 'secret');

        $useCase = $this->makeUseCase($apiClient, new SpotifyCredentials('cid', 'secret', 'http://cb', [], 'db'));
        $result = $useCase();

        $this->assertTrue($result['valid']);
        $this->assertSame('validated', $result['status']);
    }

    public function test_invalid_when_spotify_rejects_credentials(): void
    {
        $apiClient = $this->createMock(SpotifyApiClientInterface::class);
        $apiClient->method('checkClientCredentials')
            ->willThrowException(new SpotifyTokenInvalidException('invalid_client'));

        $useCase = $this->makeUseCase($apiClient, new SpotifyCredentials('cid', 'bad', 'http://cb', [], 'db'));
        $result = $useCase();

        $this->assertFalse($result['valid']);
        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('invalid_client', $result['note']);
    }

    public function test_invalid_when_incomplete_without_calling_spotify(): void
    {
        $apiClient = $this->createMock(SpotifyApiClientInterface::class);
        $apiClient->expects($this->never())->method('checkClientCredentials');

        $useCase = $this->makeUseCase($apiClient, new SpotifyCredentials('', '', '', [], 'env'));
        $result = $useCase();

        $this->assertFalse($result['valid']);
    }

    private function makeUseCase(SpotifyApiClientInterface $apiClient, SpotifyCredentials $creds): ValidateSpotifyAppConfig
    {
        $configRepo = $this->createMock(SpotifyAppConfigRepositoryInterface::class);
        $configRepo->method('findActive')->willReturn(null);

        $activityLog = $this->createMock(ActivityLogRepositoryInterface::class);

        $provider = $this->createMock(SpotifyCredentialsProviderInterface::class);
        $provider->method('current')->willReturn($creds);

        return new ValidateSpotifyAppConfig($configRepo, $activityLog, $provider, $apiClient);
    }
}
