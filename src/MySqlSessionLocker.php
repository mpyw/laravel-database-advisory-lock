<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\MySqlConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\SessionLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLock;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLocker;
use WeakMap;

use function array_fill;

final class MySqlSessionLocker implements SessionLocker
{
    use SessionLocks;

    /**
     * @var WeakMap<SessionLock, bool>
     */
    private WeakMap $locks;

    public function __construct(
        private MySqlConnection $connection,
    ) {
        $this->locks = new WeakMap();
    }

    public function lockOrFail(string $key, int $timeout = 0): SessionLock
    {
        $sql = "SELECT GET_LOCK(CASE WHEN LENGTH(?) > 64 THEN CONCAT(SUBSTR(?, 1, 24), SHA1(?)) ELSE ? END, {$timeout})";
        $bindings = array_fill(0, 4, $key);

        $result = (new Selector($this->connection))
            ->selectBool($sql, $bindings, false);

        if (!$result) {
            throw new LockFailedException("Failed to acquire lock: {$key}", $sql, $bindings);
        }

        $lock = new MySqlSessionLock($this->connection, $this->locks, $key);
        $this->locks[$lock] = true;

        return $lock;
    }

    public function withLocking(string $key, callable $callback, int $timeout = 0): mixed
    {
        $lock = $this->lockOrFail($key, $timeout);

        try {
            return $callback($this->connection);
        } finally {
            $lock->release();
        }
    }

    public function hasAny(): bool
    {
        return $this->locks->count() > 0;
    }
}
