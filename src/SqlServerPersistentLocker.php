<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\SqlServerConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\PersistentlyLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockConflictException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLock;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLocker;
use WeakMap;

final class SqlServerPersistentLocker implements PersistentLocker
{
    use PersistentlyLocks;

    /**
     * @var WeakMap<PersistentLock, bool>
     */
    protected WeakMap $locks;

    public function __construct(
        private SqlServerConnection $connection,
    ) {
        $this->locks = new WeakMap();
    }

    public function acquireOrFail(string $key, int $timeout = 0): PersistentLock
    {
        $sql = "EXEC sp_getapplock ?, 'Exclusive', 'Session', {$timeout}";

        $result = (new Selector($this->connection))
            ->selectInt($sql, [$key], false);

        if ($result < 0) {
            throw new LockConflictException(
                "Failed to acquire lock: {$key}",
                $sql,
                [$key],
            );
        }

        $lock = new SqlServerPersistentLock($this->connection, $this->locks, $key);
        $this->locks[$lock] = true;

        return $lock;
    }

    public function hasAny(): bool
    {
        return $this->locks->count() > 0;
    }
}
