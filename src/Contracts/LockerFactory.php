<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

/**
 * interface LockerFactory
 *
 * Entrypoint used from the mix-in AdvisoryLocks trait.
 * Underlying locker instances are managed as singletons.
 */
interface LockerFactory
{
    /**
     * Create a transaction-level locker or return existing one.
     */
    public function forTransaction(): TransactionLocker;

    /**
     * Create a session-level locker or return existing one.
     */
    public function forSession(): SessionLocker;
}
