<?php

declare(strict_types=1);

namespace App\Tests\Module\Spotify\Application;

use App\Module\Spotify\Application\Dto\SpotifyCredentials;
use App\Module\Spotify\Application\GetSpotifyAuthorizationUrl;
use App\Module\Spotify\Application\GetSpotifyAuthorizationUrlResult;
use App\Module\Spotify\Application\Port\OAuthStateManagerInterface;
use App\Module\Spotify\Application\Port\SpotifyCredentialsProviderInterface;
use PHPUnit\Framework\TestCase;

class GetSpotifyAuthorizationUrlTest extends TestCase
{
    public function test_returns_url_with_state_and_redirect_uri(): void
    {
        $stateManager = $this->createMock(OAuthStateManagerInterface::class);
        $stateManager->method('createState')->with('profile-123')->willReturn('generated-state');

        $credentials = $this->createMock(SpotifyCredentialsProviderInterface::class);
        $credentials->method('current')->willReturn(new SpotifyCredentials(
            'test-client-id',
            'test-secret',
            'https://backend.example.com/api/v1/spotify/callback',
            ['user-read-private', 'user-modify-playback-state'],
            'db',
        ));

        $useCase = new GetSpotifyAuthorizationUrl($stateManager, $credentials);
        $result = $useCase->__invoke('profile-123');

        $this->assertInstanceOf(GetSpotifyAuthorizationUrlResult::class, $result);
        $this->assertStringContainsString('accounts.spotify.com/authorize', $result->authorizationUrl);
        $this->assertStringContainsString('client_id=test-client-id', $result->authorizationUrl);
        $this->assertStringContainsString('state=generated-state', $result->authorizationUrl);
        $this->assertStringContainsString('redirect_uri=', $result->authorizationUrl);
        $this->assertSame('generated-state', $result->state);
        $this->assertSame('https://backend.example.com/api/v1/spotify/callback', $result->redirectUri);
    }
}
