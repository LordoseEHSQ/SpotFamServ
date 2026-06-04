<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Infrastructure\Repository;

use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Domain\DetectedDevice;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDetectedDeviceRepository implements DetectedDeviceRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findByMac(string $mac): ?DetectedDevice
    {
        return $this->em->getRepository(DetectedDevice::class)->findOneBy(['mac' => $mac]);
    }

    public function findById(string $id): ?DetectedDevice
    {
        return $this->em->getRepository(DetectedDevice::class)->find($id);
    }

    /** @return list<DetectedDevice> */
    public function findAll(): array
    {
        return $this->em->getRepository(DetectedDevice::class)->findBy([], ['lastSeenAt' => 'DESC']);
    }

    public function save(DetectedDevice $device): void
    {
        $this->em->persist($device);
        $this->em->flush();
    }
}
