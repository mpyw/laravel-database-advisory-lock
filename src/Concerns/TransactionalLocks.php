<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Concerns;

use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;

trait TransactionalLocks
{
    public function tryLock(string $key, int|float $timeout = 0): bool
    {
        try {
            $this->lockOrFail($key, $timeout);

            return true;
        } catch (LockFailedException) {
            return false;
        }
    }

    abstract public function lockOrFail(string $key, int|float $timeout = 0): void;
}
