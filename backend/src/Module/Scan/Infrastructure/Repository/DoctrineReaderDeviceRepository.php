<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Repository;

use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Domain\ReaderDevice;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineReaderDeviceRepository implements ReaderDeviceRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findByReaderId(string $readerId): ?ReaderDevice
    {
        return $this->em->getRepository(ReaderDevice::class)->findOneBy(['readerId' => $readerId]);
    }

    /** @return list<ReaderDevice> */
    public function findAll(): array
    {
        return $this->em->getRepository(ReaderDevice::class)->findBy([], ['readerId' => 'ASC']);
    }

    public function save(ReaderDevice $device): void
    {
        $this->em->persist($device);
        $this->em->flush();
    }

    public function delete(ReaderDevice $device): void
    {
        $this->em->remove($device);
        $this->em->flush();
    }
}
