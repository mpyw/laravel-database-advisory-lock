<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\MySqlConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\PersistentlyLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockConflictException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLock;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLocker;
use WeakMap;

use function array_fill;

final class MySqlPersistentLocker implements PersistentLocker
{
    use PersistentlyLocks;

    /**
     * @var WeakMap<PersistentLock, bool>
     */
    private WeakMap $locks;

    public function __construct(
        private MySqlConnection $connection,
    ) {
        $this->locks = new WeakMap();
    }

    public function acquireOrFail(string $key, int $timeout = 0): PersistentLock
    {
        $sql = "SELECT GET_LOCK(CASE WHEN LENGTH(?) > 64 THEN CONCAT(SUBSTR(?, 1, 24), SHA1(?)) ELSE ? END, {$timeout})";
        $bindings = array_fill(0, 4, $key);

        $result = (new Selector($this->connection))
            ->selectBool($sql, $bindings, false);

        if (!$result) {
            throw new LockConflictException("Failed to acquire lock: {$key}", $sql, $bindings);
        }

        $lock = new MySqlPersistentLock($this->connection, $this->locks, $key);
        $this->locks[$lock] = true;

        return $lock;
    }

    public function hasAny(): bool
    {
        return $this->locks->count() > 0;
    }
}
