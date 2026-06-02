<?php

declare(strict_types=1);

namespace App\Tests\Module\Spotify\Application;

use App\Module\Spotify\Application\GetSpotifyStatus;
use App\Module\Spotify\Domain\SpotifyAccountLink;
use PHPUnit\Framework\TestCase;

class GetSpotifyStatusTest extends TestCase
{
    public function test_resolve_not_connected_when_no_link(): void
    {
        $this->assertSame('not_connected', GetSpotifyStatus::resolve(null));
    }

    public function test_resolve_connected_with_expired_access_token(): void
    {
        // Expired access-token clock alone must NOT downgrade the status (#25): refresh handles it.
        $link = new SpotifyAccountLink(
            '11111111-1111-1111-1111-111111111111',
            'spotify-user',
            'access',
            'refresh',
            new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC')),
        );
        $this->assertSame('connected', GetSpotifyStatus::resolve($link));
    }

    public function test_resolve_reauth_required_when_flagged(): void
    {
        $link = new SpotifyAccountLink(
            '22222222-2222-2222-2222-222222222222',
            'spotify-user',
            'access',
            'refresh',
            new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC')),
        );
        $link->markNeedsReauth();
        $this->assertSame('reauth_required', GetSpotifyStatus::resolve($link));
    }
}
