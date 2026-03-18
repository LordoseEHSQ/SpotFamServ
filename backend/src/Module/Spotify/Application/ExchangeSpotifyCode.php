<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Dto\SpotifyTokenResponseDto;
use App\Module\Spotify\Application\Port\OAuthStateManagerInterface;
use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use App\Module\Spotify\Domain\SpotifyAccountLink;

/**
 * Validate state, exchange code for tokens, create or update account link.
 */
final readonly class ExchangeSpotifyCode
{
    public function __construct(
        private OAuthStateManagerInterface $stateManager,
        private SpotifyApiClientInterface $apiClient,
        private SpotifyAccountLinkRepositoryInterface $linkRepository,
        private SpotifyTokenManagerInterface $tokenManager,
    ) {
    }

    public function __invoke(string $code, string $state, string $redirectUri): SpotifyAccountLink
    {
        $profileId = $this->stateManager->consumeState($state);
        $dto = $this->apiClient->exchangeCode($code, $redirectUri);
        $user = $this->apiClient->getCurrentUser($dto->accessToken);
        $expiresAt = new \DateTimeImmutable('+' . $dto->expiresIn . ' seconds', new \DateTimeZone('UTC'));

        $link = $this->linkRepository->findByProfileId($profileId);
        if ($link !== null) {
            $link->updateTokens($dto->accessToken, $expiresAt, $dto->scope !== '' ? $dto->scope : null);
            if ($dto->refreshToken !== '') {
                $link->setRefreshToken($dto->refreshToken);
            }
        } else {
            $link = new SpotifyAccountLink($profileId, $user->id, $dto->accessToken, $dto->refreshToken, $expiresAt);
            if ($dto->scope !== '') {
                $link->setScopes($dto->scope);
            }
        }
        $this->linkRepository->save($link);
        return $link;
    }
}
