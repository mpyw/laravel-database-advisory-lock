<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\QueryException;

/**
 * interface TransactionLocker
 *
 * Transaction-level locker.
 * Acquired locks are implicitly released on transaction commits/rollbacks.
 */
interface TransactionLocker
{
    /**
     * Attempts to acquire a lock or returns false if failed.
     * QueryException may be thrown on connection-level errors.
     *
     * @throws QueryException
     */
    public function tryLock(string $key, int $timeout = 0): bool;

    /**
     * Attempts to acquire a lock or throw LockFailedException if failed.
     * QueryException may be thrown on connection-level errors.
     *
     * @throws LockFailedException
     * @throws QueryException
     */
    public function lockOrFail(string $key, int $timeout = 0): void;
}
