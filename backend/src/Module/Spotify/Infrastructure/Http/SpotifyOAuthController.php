<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Http;

use App\Module\Spotify\Application\ExchangeSpotifyCode;
use App\Module\Spotify\Application\Port\SpotifyCredentialsProviderInterface;
use App\Module\Spotify\Domain\Exception\SpotifyOAuthStateException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;
use App\Module\System\Application\Port\SystemConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles GET callback from Spotify (code + state in query).
 * Redirects to frontend with success or error.
 *
 * Die Frontend-URL kommt aus der System-Konfiguration (DB, sonst Env `FRONTEND_URL`),
 * damit sie ohne Re-Deploy in den Systemeinstellungen pflegbar ist (D-029).
 */
#[Route(path: '/spotify', name: 'api_spotify_oauth_', format: 'json')]
final class SpotifyOAuthController
{
    public function __construct(
        private readonly ExchangeSpotifyCode $exchangeCode,
        private readonly SpotifyCredentialsProviderInterface $credentials,
        private readonly SystemConfigurationProviderInterface $systemConfig,
    ) {
    }

    #[Route(path: '/callback', name: 'callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $frontendUrl = $this->systemConfig->getFrontendUrl();

        $code = $request->query->getString('code');
        $state = $request->query->getString('state');
        $error = $request->query->getString('error');

        if ($error !== '') {
            $fragment = 'error=' . urlencode($error) . '&message=' . urlencode($request->query->getString('error_description', 'User denied or error'));
            return new RedirectResponse($frontendUrl . '/spotify-callback?' . $fragment);
        }

        if ($code === '' || $state === '') {
            return new RedirectResponse($frontendUrl . '/spotify-callback?error=missing_params');
        }

        $redirectUri = $this->credentials->current()->redirectUri;
        try {
            $link = ($this->exchangeCode)($code, $state, $redirectUri);
            $profileId = $link->getFamilyProfileId();
            return new RedirectResponse($frontendUrl . '/profiles/' . $profileId . '?spotify=connected');
        } catch (SpotifyOAuthStateException $e) {
            return new RedirectResponse($frontendUrl . '/spotify-callback?error=invalid_state');
        } catch (SpotifyTokenInvalidException $e) {
            return new RedirectResponse($frontendUrl . '/spotify-callback?error=token_failed');
        }
    }
}
