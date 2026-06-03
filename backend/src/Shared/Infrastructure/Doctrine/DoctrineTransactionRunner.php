<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Application\Port\TransactionRunnerInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTransactionRunner implements TransactionRunnerInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function run(callable $callback): mixed
    {
        return $this->em->wrapInTransaction($callback);
    }
}
