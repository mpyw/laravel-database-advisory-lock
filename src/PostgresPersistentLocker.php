<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\PersistentlyLocks;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockConflictException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLock;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\UnsupportedDriverException;
use WeakMap;

final class PostgresPersistentLocker implements PersistentLocker
{
    use PersistentlyLocks;

    /**
     * @var WeakMap<PersistentLock, bool>
     */
    protected WeakMap $locks;

    public function __construct(
        private PostgresConnection $connection,
    ) {
        $this->locks = new WeakMap();
    }

    public function acquireOrFail(string $key, int $timeout = 0): PersistentLock
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
            throw new LockConflictException("Failed to acquire lock: {$key}", $sql, [$key]);
        }

        $lock = new PostgresPersistentLock($this->connection, $this->locks, $key);
        $this->locks[$lock] = true;

        return $lock;
    }

    public function hasAny(): bool
    {
        return $this->locks->count() > 0;
    }
}
