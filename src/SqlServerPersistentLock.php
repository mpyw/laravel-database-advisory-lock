<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\SqlServerConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\ReleasesWhenDestructed;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLock;
use WeakMap;

final class SqlServerPersistentLock implements PersistentLock
{
    use ReleasesWhenDestructed;

    private bool $released = false;

    /**
     * @param WeakMap<PersistentLock, bool> $locks
     */
    public function __construct(
        private SqlServerConnection $connection,
        private WeakMap $locks,
        private string $key,
    ) {
    }

    public function release(): bool
    {
        if (!$this->released) {
            $sql = "EXEC sp_releaseapplock ?, 'Session'";

            $this->connection->selectOne($sql, [$this->key], false);

            $this->released = (new Selector($this->connection))
                ->selectInt($sql, [$this->key], false) < 0;

            if ($this->released) {
                $this->locks->offsetUnset($this);
            }
        }

        return $this->released;
    }
}
