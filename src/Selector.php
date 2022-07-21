<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

use function array_shift;

/**
 * class Selector
 *
 * Helper utilities to retrieve results.
 *
 * @internal
 */
final class Selector
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {
    }

    /**
     * Run query to get a single value from the result.
     * QueryException may be thrown on connection-level errors.
     *
     * @throws QueryException
     */
    public function select(string $sql, array $bindings): mixed
    {
        // Always pass false to $useReadPdo
        $row = (array)$this
            ->connection
            ->selectOne($sql, $bindings, false);

        return array_shift($row);
    }
}
