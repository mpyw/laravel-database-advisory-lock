<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\MySqlConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Concerns\ReleasesWhenDestructed;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLock;
use WeakMap;

use function array_fill;

final class MySqlSessionLock implements SessionLock
{
    use ReleasesWhenDestructed;

    private bool $released = false;

    /**
     * @param WeakMap<SessionLock, bool> $locks
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
            // When key strings exceed 64 chars limit,
            // it takes first 24 chars from them and appends 40 chars `sha1()` hashes.
            $sql = 'SELECT RELEASE_LOCK(CASE WHEN CHAR_LENGTH(?) > 64 THEN CONCAT(SUBSTR(?, 1, 24), SHA1(?)) ELSE ? END)';

            $this->released = (new Selector($this->connection))
                ->selectBool($sql, array_fill(0, 4, $this->key));

            // Clean up the lock when it succeeds.
            $this->released && $this->locks->offsetUnset($this);
        }

        return $this->released;
    }
}
