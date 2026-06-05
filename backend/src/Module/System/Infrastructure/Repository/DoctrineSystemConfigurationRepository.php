<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Repository;

use App\Module\System\Application\Port\SystemConfigurationRepositoryInterface;
use App\Module\System\Domain\SystemConfiguration;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSystemConfigurationRepository implements SystemConfigurationRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findActive(): ?SystemConfiguration
    {
        return $this->em->getRepository(SystemConfiguration::class)
            ->findOneBy(['isActive' => true]);
    }

    public function save(SystemConfiguration $config): void
    {
        $this->em->persist($config);
        $this->em->flush();
    }
}
