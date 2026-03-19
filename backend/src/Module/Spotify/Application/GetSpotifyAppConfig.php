<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyAppConfigRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyAppConfiguration;

final readonly class GetSpotifyAppConfig
{
    public function __construct(
        private SpotifyAppConfigRepositoryInterface $configRepository,
        private string $envClientId,
        private string $envClientSecret,
        private string $envRedirectUri,
    ) {
    }

    /**
     * Returns the active DB config or a transient object pre-filled from env vars.
     * The returned object is annotated with isFromEnv() if it comes from env vars.
     *
     * @return array{config: SpotifyAppConfiguration, source: 'db'|'env'}
     */
    public function __invoke(): array
    {
        $config = $this->configRepository->findActive();
        if ($config !== null) {
            return ['config' => $config, 'source' => 'db'];
        }

        $fromEnv = new SpotifyAppConfiguration();
        if ($this->envClientId !== '') {
            $fromEnv->setSpotifyClientId($this->envClientId);
        }
        if ($this->envClientSecret !== '') {
            $fromEnv->setSpotifyClientSecret($this->envClientSecret);
        }
        if ($this->envRedirectUri !== '') {
            $fromEnv->setRedirectUri($this->envRedirectUri);
        }
        if ($fromEnv->isComplete()) {
            $fromEnv->setConfigStatus(SpotifyAppConfiguration::STATUS_CONFIGURED);
        }

        return ['config' => $fromEnv, 'source' => 'env'];
    }
}
