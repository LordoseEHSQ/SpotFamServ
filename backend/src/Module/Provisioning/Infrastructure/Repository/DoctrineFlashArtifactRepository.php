<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Infrastructure\Repository;

use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Domain\FlashArtifact;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFlashArtifactRepository implements FlashArtifactRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findById(string $id): ?FlashArtifact
    {
        return $this->em->getRepository(FlashArtifact::class)->find($id);
    }

    public function findByBoardChannelVersion(string $board, string $channel, string $version): ?FlashArtifact
    {
        return $this->em->getRepository(FlashArtifact::class)->findOneBy([
            'board'   => $board,
            'channel' => $channel,
            'version' => $version,
        ]);
    }

    /** @return list<FlashArtifact> */
    public function findByBoardChannel(string $board, string $channel): array
    {
        return $this->em->getRepository(FlashArtifact::class)->findBy([
            'board'   => $board,
            'channel' => $channel,
        ]);
    }

    /** @return list<FlashArtifact> */
    public function findAll(): array
    {
        return $this->em->getRepository(FlashArtifact::class)->findBy([], ['createdAt' => 'DESC']);
    }

    public function save(FlashArtifact $artifact): void
    {
        $this->em->persist($artifact);
        $this->em->flush();
    }
}
