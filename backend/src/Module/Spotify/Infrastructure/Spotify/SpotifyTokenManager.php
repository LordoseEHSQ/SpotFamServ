<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Spotify;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Spotify\Application\Dto\SpotifyTokenResponseDto;
use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;
use App\Module\Spotify\Domain\SpotifyAccountLink;
use Symfony\Component\Uid\Uuid;

/**
 * Ensures we have a valid access token for a profile; refreshes and persists when expired.
 */
final class SpotifyTokenManager implements SpotifyTokenManagerInterface
{
    /** Buffer in seconds before expiry to trigger refresh */
    private const REFRESH_BUFFER_SECONDS = 300;

    public function __construct(
        private readonly SpotifyAccountLinkRepositoryInterface $linkRepository,
        private readonly SpotifyApiClientInterface $apiClient,
        private readonly ActivityLogRepositoryInterface $activityRepository,
    ) {
    }

    public function getValidLinkForProfile(string $profileId): SpotifyAccountLink
    {
        $link = $this->linkRepository->findByProfileId($profileId);
        if ($link === null) {
            throw new SpotifyNotConnectedException();
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $link->getExpiresAt();
        if ($expiresAt->getTimestamp() - $now->getTimestamp() < self::REFRESH_BUFFER_SECONDS) {
            $this->refreshAndPersist($link);
            $link = $this->linkRepository->findByProfileId($profileId);
            if ($link === null) {
                throw new SpotifyNotConnectedException();
            }
        }
        return $link;
    }

    private function refreshAndPersist(SpotifyAccountLink $link): void
    {
        try {
            $dto = $this->apiClient->refreshToken($link->getRefreshToken());
        } catch (SpotifyTokenInvalidException $e) {
            // Permanent failure (invalid_grant / revoked) → flag for re-auth, surface to UI, rethrow.
            // Transient errors (network/5xx) are SpotifyApiException and intentionally NOT flagged.
            $link->markNeedsReauth();
            $this->linkRepository->save($link);
            $this->activityRepository->append(new ActivityLog(
                ActivityLog::TYPE_SPOTIFY_REAUTH_REQUIRED,
                'Spotify-Token-Refresh fehlgeschlagen – Neuanmeldung erforderlich.',
                ActivityLog::SEVERITY_WARNING,
                Uuid::fromString($link->getFamilyProfileId()),
                'spotify_account_link',
                (string) $link->getId(),
            ));
            throw $e;
        }

        $this->applyTokenResponse($link, $dto);
        $link->clearNeedsReauth();
        $this->linkRepository->save($link);

        $this->activityRepository->append(new ActivityLog(
            ActivityLog::TYPE_SPOTIFY_TOKEN_REFRESH,
            'Spotify-Access-Token erneuert.',
            ActivityLog::SEVERITY_DEBUG,
            Uuid::fromString($link->getFamilyProfileId()),
            'spotify_account_link',
            (string) $link->getId(),
        ));
    }

    private function applyTokenResponse(SpotifyAccountLink $link, SpotifyTokenResponseDto $dto): void
    {
        $expiresAt = new \DateTimeImmutable(
            '+' . $dto->expiresIn . ' seconds',
            new \DateTimeZone('UTC')
        );
        $link->updateTokens($dto->accessToken, $expiresAt, $dto->scope !== '' ? $dto->scope : null);
        if ($dto->refreshToken !== '') {
            $link->setRefreshToken($dto->refreshToken);
        }
    }

}
