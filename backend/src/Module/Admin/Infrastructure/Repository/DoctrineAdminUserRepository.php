<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Repository;

use App\Module\Admin\Application\Port\AdminUserRepositoryInterface;
use App\Module\Admin\Domain\AdminUser;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAdminUserRepository implements AdminUserRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findByUsername(string $username): ?AdminUser
    {
        return $this->em->getRepository(AdminUser::class)->findOneBy(['username' => $username]);
    }

    public function save(AdminUser $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
