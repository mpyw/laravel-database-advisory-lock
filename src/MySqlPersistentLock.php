<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\MySqlConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\ReleasesWhenDestructed;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLock;
use WeakMap;

use function array_fill;

final class MySqlPersistentLock implements PersistentLock
{
    use ReleasesWhenDestructed;

    private bool $released = false;

    /**
     * @param WeakMap<PersistentLock, bool> $locks
     */
    public function __construct(
        private MySqlConnection $connection,
        private WeakMap $locks,
        private string $key,
    ) {
    }

    public function release(): bool
    {
        if (!$this->released) {
            $sql = 'SELECT RELEASE_LOCK(CASE WHEN LENGTH(?) > 64 THEN CONCAT(SUBSTR(?, 1, 24), SHA1(?)) ELSE ? END)';

            $this->released = (new Selector($this->connection))
                ->selectBool($sql, array_fill(0, 4, $this->key), false);

            if ($this->released) {
                $this->locks->offsetUnset($this);
            }
        }

        return $this->released;
    }
}
