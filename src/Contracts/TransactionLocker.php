<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\QueryException;

interface TransactionLocker
{
    /**
     * @throws QueryException
     */
    public function tryLock(string $key, int $timeout = 0): bool;

    /**
     * @throws LockFailedException
     * @throws QueryException
     */
    public function lockOrFail(string $key, int $timeout = 0): void;
}
