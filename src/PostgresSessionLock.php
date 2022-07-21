<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\ReleasesWhenDestructed;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLock;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\TransactionTerminationListener;
use PDOException;
use WeakMap;

final class PostgresSessionLock implements SessionLock, TransactionTerminationListener
{
    use ReleasesWhenDestructed;

    private bool $released = false;
    private ?TransactionEventHub $hub;

    /**
     * @param WeakMap<SessionLock, bool> $locks
     */
    public function __construct(
        private PostgresConnection $connection,
        private WeakMap $locks,
        private string $key,
    ) {
        $this->hub = TransactionEventHub::resolve();
        $this->hub?->initializeWithDispatcher($this->connection->getEventDispatcher());
    }

    public function release(): bool
    {
        if (!$this->released) {
            try {
                $this->released = (bool)(new Selector($this->connection))
                    ->select('SELECT pg_advisory_unlock(hashtext(?))', [$this->key]);
            } catch (PDOException $e) {
                // Postgres can't release session-level locks immediately
                // when an error occurs within a transaction.
                // Register onTransactionTerminated() for releasing
                // after the transaction is terminated or rewinding to a savepoint.
                self::causedByTransactionAbort($e)
                    ? $this->hub?->registerOnceListener($this)
                    : throw $e;
            }

            // Clean up the lock when it succeeds.
            $this->released && $this->locks->offsetUnset($this);
        }

        return $this->released;
    }

    /**
     * @see https://www.postgresql.org/docs/current/errcodes-appendix.html
     */
    private static function causedByTransactionAbort(PDOException $e): bool
    {
        return $e->getCode() === '25P02';
    }

    public function onTransactionTerminated(TransactionCommitted|TransactionRolledBack $event): void
    {
        $this->release();
    }
}
