<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\SessionLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLock;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\UnsupportedDriverException;
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

    public function lockOrFail(string $key, int $timeout = 0): SessionLock
    {
        if ($timeout !== 0) {
            // @codeCoverageIgnoreStart
            throw new UnsupportedDriverException('Timeout feature is not supported');
            // @codeCoverageIgnoreEnd
        }

        $sql = 'SELECT pg_try_advisory_lock(hashtext(?))';

        $result = (new Selector($this->connection))
            ->selectBool($sql, [$key], false);

        if (!$result) {
            throw new LockFailedException("Failed to acquire lock: {$key}", $sql, [$key]);
        }

        $lock = new PostgresSessionLock($this->connection, $this->locks, $key);
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
