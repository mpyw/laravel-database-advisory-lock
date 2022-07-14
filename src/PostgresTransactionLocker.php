<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\TransactionalLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\InvalidTransactionLevelException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\TransactionLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\UnsupportedDriverException;

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

        if ($timeout !== 0) {
            // @codeCoverageIgnoreStart
            throw new UnsupportedDriverException('Timeout feature is not supported');
            // @codeCoverageIgnoreEnd
        }

        $sql = 'SELECT pg_try_advisory_xact_lock(hashtext(?))';

        $result = (new Selector($this->connection))
            ->selectBool($sql, [$key]);

        if (!$result) {
            throw new LockFailedException("Failed to acquire lock: {$key}", $sql, [$key]);
        }
    }
}
