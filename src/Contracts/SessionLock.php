<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\QueryException;

/**
 * interface SessionLock
 *
 * Acquired through SessionLocker.
 */
interface SessionLock
{
    /**
     * Explicitly releases the lock.
     * If successful, nothing happens the second time or later.
     * QueryException may be thrown on connection-level errors.
     *
     * @throws QueryException
     */
    public function release(): bool;

    /**
     * Implicitly releases the lock on the object destruction.
     * If it has already been explicitly released by release(), nothing will happen.
     * QueryException may be thrown on connection-level errors.
     *
     * @throws QueryException
     */
    public function __destruct();
}
