<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Concerns;

use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLock;

/**
 * @internal
 */
trait SessionLocks
{
    abstract public function lockOrFail(string $key, int $timeout = 0): SessionLock;

    public function tryLock(string $key, int $timeout = 0): ?SessionLock
    {
        try {
            return $this->lockOrFail($key, $timeout);
        } catch (LockFailedException) {
            return null;
        }
    }
}
