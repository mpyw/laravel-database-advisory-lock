<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;

/**
 * interface TransactionTerminationListener
 *
 * Some drivers can't release session-level locks immediately when an error occurs within a transaction.
 * This listener is used for releasing after the transaction is terminated or rewinding to a savepoint.
 */
interface TransactionTerminationListener
{
    /**
     * A listener function.
     */
    public function onTransactionTerminated(TransactionCommitted|TransactionRolledBack $event): void;
}
