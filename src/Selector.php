<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

/**
 * @internal
 */
final class Selector
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {
    }

    /**
     * @throws QueryException
     */
    public function selectBool(string $sql, array $bindings, bool $useReadPdo = true): bool
    {
        return (bool)current(
            (array)$this
                ->connection
                ->selectOne($sql, $bindings, $useReadPdo),
        );
    }

    /**
     * @throws QueryException
     */
    public function selectInt(string $sql, array $bindings, bool $useReadPdo = true): int
    {
        $value = current(
            (array)$this
                ->connection
                ->selectOne($sql, $bindings, $useReadPdo),
        );

        return $value === null ? -999 : (int)$value;
    }
}
