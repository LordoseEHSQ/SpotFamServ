<?php

declare(strict_types=1);

namespace App\Tests\Module\Spotify\Infrastructure\Spotify;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;
use App\Module\Spotify\Application\Dto\SpotifyTokenResponseDto;
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

    public function test_refresh_failure_marks_needs_reauth_and_rethrows(): void
    {
        $profileId = '11111111-1111-1111-1111-111111111111';
        $expiresAt = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $link = new SpotifyAccountLink($profileId, 'spotify-user', 'access', 'refresh', $expiresAt);
        $repo = $this->createMock(SpotifyAccountLinkRepositoryInterface::class);
        $repo->method('findByProfileId')->willReturn($link);
        $repo->expects($this->once())->method('save')->with($link);
        $api = $this->createMock(SpotifyApiClientInterface::class);
        $api->method('refreshToken')->willThrowException(new SpotifyTokenInvalidException('invalid_grant'));
        $manager = new SpotifyTokenManager(
            $repo,
            $api,
            $this->createMock(ActivityLogRepositoryInterface::class),
        );

        try {
            $manager->getValidLinkForProfile($profileId);
            $this->fail('Expected SpotifyTokenInvalidException');
        } catch (SpotifyTokenInvalidException) {
            // expected
        }

        $this->assertTrue($link->needsReauth());
    }

    public function test_successful_refresh_clears_needs_reauth(): void
    {
        $profileId = '22222222-2222-2222-2222-222222222222';
        $expiresAt = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $link = new SpotifyAccountLink($profileId, 'spotify-user', 'access', 'refresh', $expiresAt);
        $link->markNeedsReauth();
        $this->assertTrue($link->needsReauth());

        $repo = $this->createMock(SpotifyAccountLinkRepositoryInterface::class);
        $repo->method('findByProfileId')->willReturn($link);
        $api = $this->createMock(SpotifyApiClientInterface::class);
        $api->method('refreshToken')->willReturn(
            new SpotifyTokenResponseDto('new-access', '', 3600, '')
        );
        $manager = new SpotifyTokenManager(
            $repo,
            $api,
            $this->createMock(ActivityLogRepositoryInterface::class),
        );

        $manager->getValidLinkForProfile($profileId);

        $this->assertFalse($link->needsReauth());
    }
}
