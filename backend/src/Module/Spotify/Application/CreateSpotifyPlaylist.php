<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Spotify\Application\Dto\SpotifyPlaylistDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CreateSpotifyPlaylist
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
        private ActivityLogRepositoryInterface $activityLog,
    ) {
    }

    public function __invoke(string $profileId, string $name, ?string $description = null): SpotifyPlaylistDto
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        $playlist = $this->apiClient->createPlaylist(
            $link->getAccessToken(),
            $link->getSpotifyUserId(),
            $name,
            $description,
        );

        $entry = new ActivityLog(
            ActivityLog::TYPE_PLAYLIST_CREATED,
            sprintf('Playlist "%s" erstellt', $name),
            ActivityLog::SEVERITY_INFO,
            Uuid::fromString($profileId),
            'spotify_playlist',
            $playlist->id,
            ['playlist_name' => $playlist->name],
        );
        $this->activityLog->append($entry);

        return $playlist;
    }
}
