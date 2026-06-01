<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Shared\Application\Exception\NotFoundException;

/**
 * Sets (or clears) the default Spotify playback device for a profile.
 * Decoupled from the device-governance inventory (AssignDevice) per D-009:
 * this only controls the playback target stored on the profile.
 */
final readonly class SetDefaultDevice
{
    public function __construct(
        private FamilyProfileRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $profileId, ?string $deviceId, ?string $deviceName = null): FamilyProfile
    {
        $profile = $this->repository->find($profileId);
        if ($profile === null) {
            throw new NotFoundException('Profile not found.');
        }

        $profile->setDefaultDevice(
            $deviceId !== null ? trim($deviceId) : null,
            $deviceName !== null ? trim($deviceName) : null,
        );
        $this->repository->save($profile);

        return $profile;
    }
}
