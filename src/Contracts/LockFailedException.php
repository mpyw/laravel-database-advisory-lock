<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\QueryException;
use ReflectionMethod;
use RuntimeException;

/**
 * class LockFailedException
 *
 * Lock acquisition has been failed.
 */
class LockFailedException extends QueryException
{
    public function __construct(string $connectionName, string $message, string $sql, array $bindings)
    {
        $previous = new RuntimeException($message);

        // Laravel 10 newly introduces $connectionName parameter
        // https://github.com/laravel/framework/pull/43190
        $args = (new ReflectionMethod(parent::class, __FUNCTION__))->getNumberOfParameters() > 3
            ? [$connectionName, $sql, $bindings, $previous]
            : [$sql, $bindings, $previous];

        // @phpstan-ignore-next-line
        parent::__construct(...$args);
    }
}
