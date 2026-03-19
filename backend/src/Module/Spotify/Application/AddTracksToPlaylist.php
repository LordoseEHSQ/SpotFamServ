<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AddTracksToPlaylist
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
        private ActivityLogRepositoryInterface $activityLog,
    ) {
    }

    /**
     * @param list<string> $trackUris
     */
    public function __invoke(string $profileId, string $playlistId, array $trackUris): void
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        $this->apiClient->addTracksToPlaylist($link->getAccessToken(), $playlistId, $trackUris);

        $entry = new ActivityLog(
            ActivityLog::TYPE_PLAYLIST_CHANGED,
            sprintf('%d Track(s) zu Playlist hinzugefügt', count($trackUris)),
            ActivityLog::SEVERITY_INFO,
            Uuid::fromString($profileId),
            'spotify_playlist',
            $playlistId,
            ['track_count' => count($trackUris)],
        );
        $this->activityLog->append($entry);
    }
}
