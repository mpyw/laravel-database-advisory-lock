<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\SessionLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLock;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Utilities\PostgresTryLockLoopEmulator;
use Mpyw\LaravelDatabaseAdvisoryLock\Utilities\Selector;
use WeakMap;

final class PostgresSessionLocker implements SessionLocker
{
    use SessionLocks;

    /**
     * @var WeakMap<SessionLock, bool>
     */
    protected WeakMap $locks;

    public function __construct(
        private PostgresConnection $connection,
    ) {
        $this->locks = new WeakMap();
    }

    /**
     * {@inheritDoc}
     *
     * Use of this method is strongly discouraged in Postgres. Use withLocking() instead.
     */
    public function lockOrFail(string $key, int $timeout = 0): SessionLock
    {
        if ($timeout > 0) {
            // Positive timeout can be emulated through repeating sleep and retry
            $emulator = new PostgresTryLockLoopEmulator($this->connection);
            $sql = $emulator->sql($timeout, false);
            $result = $emulator->performTryLockLoop($key, $timeout);
        } else {
            // Negative timeout means infinite wait
            // Zero timeout means no wait
            $sql = $timeout < 0
                ? "SELECT pg_advisory_lock(hashtext(?))::text = ''"
                : 'SELECT pg_try_advisory_lock(hashtext(?))';

            $selector = new Selector($this->connection);
            $result = (bool)$selector->select($sql, [$key]);
        }

        if (!$result) {
            throw new LockFailedException(
                (string)$this->connection->getName(),
                "Failed to acquire lock: {$key}",
                $sql,
                [$key],
            );
        }

        // Register the lock when it succeeds.
        $lock = new PostgresSessionLock($this->connection, $this->locks, $key);
        $this->locks[$lock] = true;

        return $lock;
    }

    public function withLocking(string $key, callable $callback, int $timeout = 0): mixed
    {
        $lock = $this->lockOrFail($key, $timeout);

        try {
            // In Postgres, savepoints allow recovery from errors.
            // This ensures release() on finally.
            /** @noinspection PhpUnhandledExceptionInspection */
            return $this->connection->transactionLevel() > 0
                ? $this->connection->transaction(fn () => $callback($this->connection))
                : $callback($this->connection);
        } finally {
            $lock->release();
        }
    }

    public function hasAny(): bool
    {
        return $this->locks->count() > 0;
    }
}
