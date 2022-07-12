<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\TransactionAwareLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockConflictException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\TransactionAwareLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\UnsupportedDriverException;

final class PostgresTransactionAwareLocker implements TransactionAwareLocker
{
    use TransactionAwareLocks;

    public function __construct(
        protected PostgresConnection $connection,
    ) {
    }

    public function lockOrFail(string $key, int $timeout = 0): void
    {
        if ($timeout !== 0) {
            throw new UnsupportedDriverException('Timeout feature is not supported');
        }

        $sql = 'SELECT pg_try_advisory_xact_lock(hashtext(?))';

        $result = (new Selector($this->connection))
            ->selectBool($sql, [$key], false);

        if (!$result) {
            throw new LockConflictException("Failed to acquire lock: {$key}", $sql, [$key]);
        }
    }
}
