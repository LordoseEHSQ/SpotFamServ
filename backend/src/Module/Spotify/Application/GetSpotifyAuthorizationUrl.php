<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\OAuthStateManagerInterface;

final readonly class GetSpotifyAuthorizationUrl
{
    public function __construct(
        private OAuthStateManagerInterface $stateManager,
        private string $clientId,
        private string $redirectUri,
    ) {
    }

    /**
     * Build Spotify authorization URL for the profile. Profile context is stored in state.
     */
    public function __invoke(string $profileId): GetSpotifyAuthorizationUrlResult
    {
        $state = $this->stateManager->createState($profileId);
        $redirectUri = $this->redirectUri;
        $params = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(' ', [
                'user-read-private',
                'user-read-email',
                'playlist-read-private',
                'playlist-modify-public',
                'playlist-modify-private',
                'user-modify-playback-state',
                'user-read-playback-state',
            ]),
            'show_dialog' => 'false',
        ]);
        $url = 'https://accounts.spotify.com/authorize?' . $params;
        return new GetSpotifyAuthorizationUrlResult($url, $state, $redirectUri);
    }
}
