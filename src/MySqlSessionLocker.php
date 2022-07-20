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
        // When key strings exceed 64 chars limit,
        // it takes first 24 chars from them and appends 40 chars `sha1()` hashes.
        $sql = "SELECT GET_LOCK(CASE WHEN CHAR_LENGTH(?) > 64 THEN CONCAT(SUBSTR(?, 1, 24), SHA1(?)) ELSE ? END, {$timeout})";
        $bindings = array_fill(0, 4, $key);

        $result = (new Selector($this->connection))
            ->selectBool($sql, $bindings);

        if (!$result) {
            throw new LockFailedException(
                (string)$this->connection->getName(),
                "Failed to acquire lock: {$key}",
                $sql,
                $bindings,
            );
        }

        // Register the lock when it succeeds.
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
