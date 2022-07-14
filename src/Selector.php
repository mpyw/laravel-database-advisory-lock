<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

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
     * Run query to get a boolean from the result.
     * Illegal values are regarded as false.
     * QueryException may be thrown on connection-level errors.
     *
     * @throws QueryException
     */
    public function selectBool(string $sql, array $bindings): bool
    {
        // Always pass false to $useReadPdo
        return (bool)current(
            (array)$this
                ->connection
                ->selectOne($sql, $bindings, false),
        );
    }
}
