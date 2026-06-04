<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Port;

use App\Module\Admin\Domain\AdminUser;

interface AdminUserRepositoryInterface
{
    public function findByUsername(string $username): ?AdminUser;

    public function save(AdminUser $user): void;
}
