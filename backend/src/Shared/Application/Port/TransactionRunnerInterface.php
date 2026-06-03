<?php

declare(strict_types=1);

namespace App\Shared\Application\Port;

interface TransactionRunnerInterface
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
