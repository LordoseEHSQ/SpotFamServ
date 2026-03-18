<?php

declare(strict_types=1);

namespace App\Module\ActivityLog\Infrastructure\Http;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('')]
final class ActivityLogController extends AbstractController
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityRepository,
        private readonly FamilyProfileRepositoryInterface $profileRepository,
    ) {}

    #[Route('/activity-log', name: 'api_activity_log_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $profileIdRaw = $request->query->get('profile_id');
        $severity     = $request->query->get('severity');
        $limit        = min((int) ($request->query->get('limit', 50)), 200);
        $offset       = (int) ($request->query->get('offset', 0));

        $profileUuid = null;
        if ($profileIdRaw !== null) {
            try {
                $profileUuid = Uuid::fromString($profileIdRaw);
            } catch (\Throwable) {
                // invalid UUID → ignore filter
            }
        }

        $entries = $this->activityRepository->findRecent($profileUuid, $severity, $limit, $offset);
        $total   = $this->activityRepository->countRecent($profileUuid, $severity);

        $profileNames = [];

        return $this->json([
            'items' => array_map(function (ActivityLog $e) use (&$profileNames): array {
                $profileName = null;
                if ($e->getFamilyProfileId() !== null) {
                    $profileIdStr = (string) $e->getFamilyProfileId();
                    if (!isset($profileNames[$profileIdStr])) {
                        $profile = $this->profileRepository->find((string) $e->getFamilyProfileId());
                        $profileNames[$profileIdStr] = $profile?->getName();
                    }
                    $profileName = $profileNames[$profileIdStr];
                }

                return [
                    'id'                   => (string) $e->getId(),
                    'family_profile_id'    => $e->getFamilyProfileId() !== null ? (string) $e->getFamilyProfileId() : null,
                    'profile_name'         => $profileName,
                    'related_entity_type'  => $e->getRelatedEntityType(),
                    'related_entity_id'    => $e->getRelatedEntityId(),
                    'activity_type'        => $e->getActivityType(),
                    'severity'             => $e->getSeverity(),
                    'message'              => $e->getMessage(),
                    'details'              => $e->getDetails(),
                    'occurred_at'          => $e->getOccurredAt()->format(\DateTimeInterface::ATOM),
                ];
            }, $entries),
            'total' => $total,
        ]);
    }
}
