<?php

declare(strict_types=1);

namespace App\Module\Rfid\Infrastructure\Repository;

use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Rfid\Domain\RfidCard;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRfidCardRepository implements RfidCardRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<RfidCard> */
    public function findByProfileId(string $profileId): array
    {
        return $this->em->getRepository(RfidCard::class)->findBy(
            ['familyProfileId' => $profileId],
            ['cardUid' => 'ASC'],
        );
    }

    public function findById(string $id): ?RfidCard
    {
        return $this->em->find(RfidCard::class, $id);
    }

    public function findByCardUid(string $cardUid): ?RfidCard
    {
        return $this->em->getRepository(RfidCard::class)->findOneBy(['cardUid' => $cardUid]);
    }

    public function remove(RfidCard $card): void
    {
        $this->em->remove($card);
        $this->em->flush();
    }

    public function save(RfidCard $card): void
    {
        $this->em->persist($card);
        $this->em->flush();
    }
}
