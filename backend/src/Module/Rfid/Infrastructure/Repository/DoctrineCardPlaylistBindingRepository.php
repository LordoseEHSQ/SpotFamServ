<?php

declare(strict_types=1);

namespace App\Module\Rfid\Infrastructure\Repository;

use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Domain\CardPlaylistBinding;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCardPlaylistBindingRepository implements CardPlaylistBindingRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findByCardId(string $rfidCardId): ?CardPlaylistBinding
    {
        return $this->em->getRepository(CardPlaylistBinding::class)->findOneBy(['rfidCardId' => $rfidCardId]);
    }

    public function save(CardPlaylistBinding $binding): void
    {
        $this->em->persist($binding);
        $this->em->flush();
    }

    public function remove(CardPlaylistBinding $binding): void
    {
        $this->em->remove($binding);
        $this->em->flush();
    }
}
