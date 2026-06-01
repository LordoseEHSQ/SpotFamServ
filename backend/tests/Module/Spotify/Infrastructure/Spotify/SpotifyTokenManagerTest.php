<?php

declare(strict_types=1);

namespace App\Tests\Module\Spotify\Infrastructure\Spotify;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\SpotifyAccountLink;
use App\Module\Spotify\Infrastructure\Spotify\SpotifyTokenManager;
use PHPUnit\Framework\TestCase;

class SpotifyTokenManagerTest extends TestCase
{
    public function test_throws_when_no_link(): void
    {
        $repo = $this->createMock(SpotifyAccountLinkRepositoryInterface::class);
        $repo->method('findByProfileId')->willReturn(null);
        $manager = new SpotifyTokenManager(
            $repo,
            $this->createMock(SpotifyApiClientInterface::class),
            $this->createMock(ActivityLogRepositoryInterface::class),
        );
        $this->expectException(SpotifyNotConnectedException::class);
        $manager->getValidLinkForProfile('profile-1');
    }

    public function test_returns_link_when_not_expired(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC'));
        $link = new SpotifyAccountLink('profile-1', 'spotify-user', 'access', 'refresh', $expiresAt);
        $repo = $this->createMock(SpotifyAccountLinkRepositoryInterface::class);
        $repo->method('findByProfileId')->willReturn($link);
        $manager = new SpotifyTokenManager(
            $repo,
            $this->createMock(SpotifyApiClientInterface::class),
            $this->createMock(ActivityLogRepositoryInterface::class),
        );
        $result = $manager->getValidLinkForProfile('profile-1');
        $this->assertSame($link, $result);
    }
}
