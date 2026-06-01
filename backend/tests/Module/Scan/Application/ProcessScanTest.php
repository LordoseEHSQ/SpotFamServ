<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Module\Scan\Application\Port\PlaybackSessionStoreInterface;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\Port\ScanCardResolverInterface;
use App\Module\Scan\Application\Port\ScanEventRepositoryInterface;
use App\Module\Scan\Application\ProcessScan;
use App\Module\Scan\Domain\ScanCardContext;
use App\Module\Scan\Domain\ScanOutcome;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use App\Module\Spotify\Application\StartPlayback;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\SpotifyAccountLink;
use PHPUnit\Framework\TestCase;

class ProcessScanTest extends TestCase
{
    private SpotifyTokenManagerInterface $tokenManager;
    private SpotifyApiClientInterface $apiClient;
    private FamilyProfileRepositoryInterface $profileRepo;
    private ScanEventRepositoryInterface $scanEvents;
    private ScanCardResolverInterface $resolver;
    private ReaderDeviceRepositoryInterface $readerDevices;
    private PlaybackSessionStoreInterface $sessionStore;

    protected function setUp(): void
    {
        $this->tokenManager = $this->createMock(SpotifyTokenManagerInterface::class);
        $this->apiClient = $this->createMock(SpotifyApiClientInterface::class);
        $this->profileRepo = $this->createMock(FamilyProfileRepositoryInterface::class);
        $this->scanEvents = $this->createMock(ScanEventRepositoryInterface::class);
        $this->resolver = $this->createMock(ScanCardResolverInterface::class);
        $this->readerDevices = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $this->sessionStore = $this->createMock(PlaybackSessionStoreInterface::class);
        $this->scanEvents->method('findRecentScan')->willReturn(null);
        $this->readerDevices->method('findByReaderId')->willReturn(null);
    }

    private function processScan(): ProcessScan
    {
        $startPlayback = new StartPlayback($this->tokenManager, $this->apiClient, $this->profileRepo);
        return new ProcessScan($this->scanEvents, $this->resolver, $this->readerDevices, $startPlayback, $this->sessionStore);
    }

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

    public function test_empty_card_uid_is_invalid_request(): void
    {
        $result = $this->processScan()->__invoke('reader-1', '   ');
        $this->assertSame(ScanOutcome::INVALID_REQUEST, $result->outcome);
    }

    public function test_unknown_card(): void
    {
        $this->resolver->method('resolveCard')->willReturn(null);
        $result = $this->processScan()->__invoke('reader-1', 'ABCD1234');
        $this->assertSame(ScanOutcome::UNKNOWN_CARD, $result->outcome);
    }

    public function test_success_starts_playback(): void
    {
        $this->resolver->method('resolveCard')->willReturn(
            new ScanCardContext('card-1', 'profile-1', 'spotify:playlist:abc')
        );
        $this->tokenManager->method('getValidLinkForProfile')->willReturn($this->link());
        $profile = new FamilyProfile('Kids', null);
        $profile->setDefaultDevice('device-1', 'Wobie Box');
        $this->profileRepo->method('find')->willReturn($profile);
        $this->sessionStore->expects($this->once())->method('remember')->with('profile-1', 'reader-1');

        $result = $this->processScan()->__invoke('reader-1', 'ABCD1234');
        $this->assertSame(ScanOutcome::SUCCESS, $result->outcome);
    }

    public function test_no_default_device_yields_no_device(): void
    {
        $this->resolver->method('resolveCard')->willReturn(
            new ScanCardContext('card-1', 'profile-1', 'spotify:playlist:abc')
        );
        $this->tokenManager->method('getValidLinkForProfile')->willReturn($this->link());
        $this->profileRepo->method('find')->willReturn(new FamilyProfile('Kids', null));

        $result = $this->processScan()->__invoke('reader-1', 'ABCD1234');
        $this->assertSame(ScanOutcome::NO_DEVICE, $result->outcome);
    }

    public function test_not_connected_yields_token_invalid(): void
    {
        $this->resolver->method('resolveCard')->willReturn(
            new ScanCardContext('card-1', 'profile-1', 'spotify:playlist:abc')
        );
        $this->tokenManager->method('getValidLinkForProfile')
            ->willThrowException(new SpotifyNotConnectedException());

        $result = $this->processScan()->__invoke('reader-1', 'ABCD1234');
        $this->assertSame(ScanOutcome::TOKEN_INVALID, $result->outcome);
    }
}
