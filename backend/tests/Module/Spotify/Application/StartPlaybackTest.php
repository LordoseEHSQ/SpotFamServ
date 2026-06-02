<?php

declare(strict_types=1);

namespace App\Tests\Module\Spotify\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Module\Spotify\Application\Dto\SpotifyDeviceDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use App\Module\Spotify\Application\StartPlayback;
use App\Module\Spotify\Domain\Exception\SpotifyNoDeviceException;
use App\Module\Spotify\Domain\SpotifyAccountLink;
use PHPUnit\Framework\TestCase;

class StartPlaybackTest extends TestCase
{
    private function link(): SpotifyAccountLink
    {
        return new SpotifyAccountLink(
            'profile-1',
            'spotify-user',
            'access-token',
            'refresh-token',
            new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC')),
        );
    }

    public function test_throws_no_device_when_no_default_and_no_explicit(): void
    {
        $tokenManager = $this->createMock(SpotifyTokenManagerInterface::class);
        $tokenManager->method('getValidLinkForProfile')->willReturn($this->link());
        $apiClient = $this->createMock(SpotifyApiClientInterface::class);
        $repo = $this->createMock(FamilyProfileRepositoryInterface::class);
        $repo->method('find')->willReturn(new FamilyProfile('Kids', null));

        $useCase = new StartPlayback($tokenManager, $apiClient, $repo);
        $this->expectException(SpotifyNoDeviceException::class);
        $useCase->__invoke('profile-1', 'spotify:playlist:abc', null);
    }

    public function test_reresolves_stale_default_device_by_name_and_retries(): void
    {
        $profile = new FamilyProfile('Kids', null);
        $profile->setDefaultDevice('stale-id', 'Connect Box');

        $tokenManager = $this->createMock(SpotifyTokenManagerInterface::class);
        $tokenManager->method('getValidLinkForProfile')->willReturn($this->link());

        $apiClient = $this->createMock(SpotifyApiClientInterface::class);
        // First transfer (stale id) fails as device-not-found; second transfer (fresh id) succeeds.
        $apiClient->method('transferPlayback')->willReturnCallback(
            function (string $token, string $deviceId): void {
                if ($deviceId === 'stale-id') {
                    throw new SpotifyNoDeviceException('Device not found');
                }
            }
        );
        $apiClient->method('getAvailableDevices')->willReturn([
            new SpotifyDeviceDto('fresh-id', 'Connect Box', 'speaker', false),
        ]);
        $apiClient->expects($this->once())
            ->method('startPlayback')
            ->with('access-token', 'spotify:playlist:abc', 'fresh-id');

        $repo = $this->createMock(FamilyProfileRepositoryInterface::class);
        $repo->method('find')->willReturn($profile);
        $repo->expects($this->once())->method('save')->with($profile);

        $useCase = new StartPlayback($tokenManager, $apiClient, $repo);
        $useCase->__invoke('profile-1', 'spotify:playlist:abc', null);

        $this->assertSame('fresh-id', $profile->getDefaultSpotifyDeviceId());
        $this->assertSame('Connect Box', $profile->getDefaultDeviceName());
    }

    public function test_rethrows_when_no_matching_device_name(): void
    {
        $profile = new FamilyProfile('Kids', null);
        $profile->setDefaultDevice('stale-id', 'Connect Box');

        $tokenManager = $this->createMock(SpotifyTokenManagerInterface::class);
        $tokenManager->method('getValidLinkForProfile')->willReturn($this->link());

        $apiClient = $this->createMock(SpotifyApiClientInterface::class);
        $apiClient->method('transferPlayback')->willThrowException(new SpotifyNoDeviceException('Device not found'));
        $apiClient->method('getAvailableDevices')->willReturn([
            new SpotifyDeviceDto('other-id', 'Kitchen', 'speaker', false),
        ]);

        $repo = $this->createMock(FamilyProfileRepositoryInterface::class);
        $repo->method('find')->willReturn($profile);

        $useCase = new StartPlayback($tokenManager, $apiClient, $repo);
        $this->expectException(SpotifyNoDeviceException::class);
        $useCase->__invoke('profile-1', 'spotify:playlist:abc', null);
    }

    public function test_explicit_device_id_is_used_without_reresolve(): void
    {
        $tokenManager = $this->createMock(SpotifyTokenManagerInterface::class);
        $tokenManager->method('getValidLinkForProfile')->willReturn($this->link());

        $apiClient = $this->createMock(SpotifyApiClientInterface::class);
        $apiClient->expects($this->once())->method('transferPlayback')->with('access-token', 'explicit-id');
        $apiClient->expects($this->once())->method('startPlayback')->with('access-token', 'spotify:playlist:abc', 'explicit-id');
        $apiClient->expects($this->never())->method('getAvailableDevices');

        $repo = $this->createMock(FamilyProfileRepositoryInterface::class);
        $repo->expects($this->never())->method('find');

        $useCase = new StartPlayback($tokenManager, $apiClient, $repo);
        $useCase->__invoke('profile-1', 'spotify:playlist:abc', 'explicit-id');
    }
}
