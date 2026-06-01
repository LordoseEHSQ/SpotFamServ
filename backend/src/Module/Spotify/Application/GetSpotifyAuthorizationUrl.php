<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\OAuthStateManagerInterface;
use App\Module\Spotify\Application\Port\SpotifyCredentialsProviderInterface;

final readonly class GetSpotifyAuthorizationUrl
{
    public function __construct(
        private OAuthStateManagerInterface $stateManager,
        private SpotifyCredentialsProviderInterface $credentials,
    ) {
    }

    /**
     * Build Spotify authorization URL for the profile. Profile context is stored in state.
     * Client ID, Redirect URI und Scopes stammen aus der effektiven Config (DB vor env).
     */
    public function __invoke(string $profileId): GetSpotifyAuthorizationUrlResult
    {
        $creds = $this->credentials->current();
        $state = $this->stateManager->createState($profileId);
        $redirectUri = $creds->redirectUri;
        $params = http_build_query([
            'client_id' => $creds->clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(' ', $creds->scopes),
            'show_dialog' => 'true',
        ]);
        $url = 'https://accounts.spotify.com/authorize?' . $params;
        return new GetSpotifyAuthorizationUrlResult($url, $state, $redirectUri);
    }
}
