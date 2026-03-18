<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Http;

use App\Module\Spotify\Application\ExchangeSpotifyCode;
use App\Module\Spotify\Domain\Exception\SpotifyOAuthStateException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles GET callback from Spotify (code + state in query).
 * Redirects to frontend with success or error.
 */
#[Route(path: '/spotify', name: 'api_spotify_oauth_', format: 'json')]
final class SpotifyOAuthController
{
    public function __construct(
        private readonly ExchangeSpotifyCode $exchangeCode,
        private readonly string $frontendUrl,
        private readonly string $redirectUri,
    ) {
    }

    #[Route(path: '/callback', name: 'callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $code = $request->query->getString('code');
        $state = $request->query->getString('state');
        $error = $request->query->getString('error');

        if ($error !== '') {
            $fragment = 'error=' . urlencode($error) . '&message=' . urlencode($request->query->getString('error_description', 'User denied or error'));
            return new RedirectResponse($this->frontendUrl . '/spotify-callback?' . $fragment);
        }

        if ($code === '' || $state === '') {
            return new RedirectResponse($this->frontendUrl . '/spotify-callback?error=missing_params');
        }

        $redirectUri = $this->redirectUri;
        try {
            $link = ($this->exchangeCode)($code, $state, $redirectUri);
            $profileId = $link->getFamilyProfileId();
            return new RedirectResponse($this->frontendUrl . '/profiles/' . $profileId . '?spotify=connected');
        } catch (SpotifyOAuthStateException $e) {
            return new RedirectResponse($this->frontendUrl . '/spotify-callback?error=invalid_state');
        } catch (SpotifyTokenInvalidException $e) {
            return new RedirectResponse($this->frontendUrl . '/spotify-callback?error=token_failed');
        }
    }
}
