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

    /**
     * @param string[] $cardIds
     * @return array<string, CardPlaylistBinding> indexed by rfid_card_id
     */
    public function findByCardIds(array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }
        $results = $this->em->getRepository(CardPlaylistBinding::class)
            ->findBy(['rfidCardId' => $cardIds]);
        $indexed = [];
        foreach ($results as $binding) {
            $indexed[$binding->getRfidCardId()] = $binding;
        }
        return $indexed;
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
