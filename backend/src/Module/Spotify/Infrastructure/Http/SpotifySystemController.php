<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Http;

use App\Module\Spotify\Application\GetSpotifyAppConfig;
use App\Module\Spotify\Application\SaveSpotifyAppConfig;
use App\Module\Spotify\Application\ValidateSpotifyAppConfig;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpunkte für die systemweite Spotify-App-Konfiguration.
 * Trennung: Client Credentials (global) vs. Benutzer-Tokens (pro Teilnehmer).
 */
#[Route(path: '/system/spotify', name: 'api_system_spotify_', format: 'json')]
final class SpotifySystemController
{
    public function __construct(
        private readonly GetSpotifyAppConfig $getConfig,
        private readonly SaveSpotifyAppConfig $saveConfig,
        private readonly ValidateSpotifyAppConfig $validateConfig,
    ) {
    }

    #[Route(path: '', name: 'config_get', methods: ['GET'])]
    public function getConfig(): JsonResponse
    {
        ['config' => $config, 'source' => $source] = ($this->getConfig)();

        return new JsonResponse([
            'source' => $source,
            'config_status' => $config->getConfigStatus(),
            'is_complete' => $config->isComplete(),
            'spotify_client_id' => $config->getSpotifyClientId(),
            'has_client_secret' => $config->getSpotifyClientSecret() !== null && $config->getSpotifyClientSecret() !== '',
            'redirect_uri' => $config->getRedirectUri(),
            'scope_defaults' => $config->getScopeDefaults(),
            'last_check_at' => $config->getLastCheckAt()?->format(\DateTimeInterface::ATOM),
            'last_check_note' => $config->getLastCheckNote(),
            'updated_at' => $config->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route(path: '', name: 'config_save', methods: ['PUT'])]
    public function saveConfig(Request $request): JsonResponse
    {
        $body = $request->toArray();

        $config = ($this->saveConfig)([
            'client_id' => isset($body['spotify_client_id']) ? (string) $body['spotify_client_id'] : null,
            'client_secret' => isset($body['spotify_client_secret']) ? (string) $body['spotify_client_secret'] : null,
            'redirect_uri' => isset($body['redirect_uri']) ? (string) $body['redirect_uri'] : null,
            'scope_defaults' => isset($body['scope_defaults']) ? (string) $body['scope_defaults'] : null,
        ]);

        return new JsonResponse([
            'config_status' => $config->getConfigStatus(),
            'is_complete' => $config->isComplete(),
            'updated_at' => $config->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route(path: '/validate', name: 'config_validate', methods: ['POST'])]
    public function validateConfig(): JsonResponse
    {
        $result = ($this->validateConfig)();
        return new JsonResponse($result);
    }
}
