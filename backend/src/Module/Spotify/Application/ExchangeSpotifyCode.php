<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Spotify\Application\Port\OAuthStateManagerInterface;
use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Domain\SpotifyAccountLink;
use Symfony\Component\Uid\Uuid;

/**
 * Validate state, exchange code for tokens, create or update account link.
 */
final readonly class ExchangeSpotifyCode
{
    public function __construct(
        private OAuthStateManagerInterface $stateManager,
        private SpotifyApiClientInterface $apiClient,
        private SpotifyAccountLinkRepositoryInterface $linkRepository,
        private ActivityLogRepositoryInterface $activityRepository,
    ) {
    }

    public function __invoke(string $code, string $state, string $redirectUri): SpotifyAccountLink
    {
        $profileId = $this->stateManager->consumeState($state);
        $dto = $this->apiClient->exchangeCode($code, $redirectUri);
        $user = $this->apiClient->getCurrentUser($dto->accessToken);
        $expiresAt = new \DateTimeImmutable('+' . $dto->expiresIn . ' seconds', new \DateTimeZone('UTC'));
        $displayName = $user->displayName !== '' ? $user->displayName : null;

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
        // Persist the human-readable display name and mark the link as validated right after consent,
        // so status/UI no longer require a separate manual validate call.
        $link->markValidated($displayName);
        // Fresh consent resolves any prior re-auth requirement (#25, D-014).
        $link->clearNeedsReauth();
        $this->linkRepository->save($link);

        $this->activityRepository->append(new ActivityLog(
            ActivityLog::TYPE_SPOTIFY_CONNECTED,
            sprintf('Spotify verbunden (%s).', $displayName ?? $user->id),
            ActivityLog::SEVERITY_INFO,
            Uuid::fromString($profileId),
            'spotify_account_link',
            (string) $link->getId(),
        ));

        return $link;
    }
}
