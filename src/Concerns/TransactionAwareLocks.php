<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Concerns;

use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockConflictException;

/**
 * @internal
 */
trait TransactionAwareLocks
{
    public function tryLock(string $key, int $timeout = 0): bool
    {
        try {
            $this->lockOrFail($key, $timeout);

            return true;
        } catch (LockConflictException) {
            return false;
        }
    }

    abstract public function lockOrFail(string $key, int $timeout = 0): void;
}
