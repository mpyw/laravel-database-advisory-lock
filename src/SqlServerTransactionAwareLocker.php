<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\SqlServerConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\TransactionAwareLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockConflictException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\TransactionAwareLocker;

final class SqlServerTransactionAwareLocker implements TransactionAwareLocker
{
    use TransactionAwareLocks;

    public function __construct(
        protected SqlServerConnection $connection,
    ) {
    }

    public function lockOrFail(string $key, int $timeout = 0): void
    {
        $sql = "EXEC sp_getapplock ?, 'Exclusive', 'Transaction', {$timeout}";

        $result = (new Selector($this->connection))
            ->selectInt($sql, [$key], false);

        if ($result < 0) {
            throw new LockConflictException(
                "Failed to acquire lock: {$key}",
                $sql,
                [$key],
            );
        }
    }
}
