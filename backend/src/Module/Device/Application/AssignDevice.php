<?php

declare(strict_types=1);

namespace App\Module\Device\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Device\Application\Port\SpotifyDeviceRepositoryInterface;
use App\Module\Device\Domain\SpotifyDevice;
use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Shared\Application\Exception\NotFoundException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class AssignDevice
{
    public function __construct(
        private SpotifyDeviceRepositoryInterface $deviceRepository,
        private FamilyProfileRepositoryInterface $profileRepository,
        private ActivityLogRepositoryInterface $activityRepository,
    ) {}

    public function __invoke(
        string $deviceId,
        ?string $profileId,
        string $mode = SpotifyDevice::ASSIGNMENT_ASSIGNED,
        ?string $note = null,
        bool $force = false,
    ): SpotifyDevice {
        $uuid   = Uuid::fromString($deviceId);
        $device = $this->deviceRepository->findById($uuid);

        if ($device === null) {
            throw new NotFoundException('Gerät nicht gefunden.');
        }

        $profileUuid = $profileId !== null ? Uuid::fromString($profileId) : null;

        if ($profileUuid !== null && $device->hasConflict($profileUuid) && !$force) {
            throw new ConflictHttpException(sprintf(
                'Das Gerät "%s" ist bereits einem anderen Teilnehmer zugeordnet. Bestätige die Übernahme explizit.',
                $device->getSpotifyDeviceName(),
            ));
        }

        $previousProfileId = $device->getAssignedFamilyProfileId();
        $device->assign($profileUuid, $mode, $note);
        $this->deviceRepository->save($device);

        $activityType = $profileUuid !== null ? ActivityLog::TYPE_DEVICE_ASSIGNED : ActivityLog::TYPE_DEVICE_UNASSIGNED;
        $message = $profileUuid !== null
            ? sprintf('Gerät "%s" wurde zugewiesen (Modus: %s).', $device->getSpotifyDeviceName(), $mode)
            : sprintf('Gerät "%s" wurde freigegeben.', $device->getSpotifyDeviceName());

        $this->activityRepository->append(new ActivityLog(
            $activityType,
            $message,
            ActivityLog::SEVERITY_INFO,
            $profileUuid,
            'spotify_device',
            (string) $device->getId(),
            [
                'previous_profile_id' => $previousProfileId !== null ? (string) $previousProfileId : null,
                'new_profile_id'      => $profileUuid !== null ? (string) $profileUuid : null,
                'mode'                => $mode,
                'forced'              => $force,
            ],
        ));

        return $device;
    }
}
