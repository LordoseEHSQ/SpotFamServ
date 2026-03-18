<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Spotify;

use App\Module\Spotify\Application\Dto\SpotifyTokenResponseDto;
use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\SpotifyAccountLink;

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
        $dto = $this->apiClient->refreshToken($link->getRefreshToken());
        $this->applyTokenResponse($link, $dto);
        $this->linkRepository->save($link);
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
