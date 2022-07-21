<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\TransactionalLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\InvalidTransactionLevelException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\TransactionLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Utilities\PostgresTryLockLoopEmulator;
use Mpyw\LaravelDatabaseAdvisoryLock\Utilities\Selector;

final class PostgresTransactionLocker implements TransactionLocker
{
    use TransactionalLocks;

    public function __construct(
        protected PostgresConnection $connection,
    ) {
    }

    public function lockOrFail(string $key, int $timeout = 0): void
    {
        if ($this->connection->transactionLevel() < 1) {
            throw new InvalidTransactionLevelException('There are no transactions');
        }

        if ($timeout > 0) {
            // Positive timeout can be emulated through repeating sleep and retry
            $emulator = new PostgresTryLockLoopEmulator($this->connection);
            $sql = $emulator->sql($timeout, false);
            $result = $emulator->performTryLockLoop($key, $timeout, true);
        } else {
            // Negative timeout means infinite wait
            // Zero timeout means no wait
            $sql = $timeout < 0
                ? "SELECT pg_advisory_xact_lock(hashtext(?))::text = ''"
                : 'SELECT pg_try_advisory_xact_lock(hashtext(?))';

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
    }
}
