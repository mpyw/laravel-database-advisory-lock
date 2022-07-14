<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * class LockFailedException
 *
 * Lock acquisition has been failed.
 */
class LockFailedException extends QueryException
{
    public function __construct(string $message, string $sql, array $bindings)
    {
        parent::__construct($sql, $bindings, new RuntimeException($message));
    }
}
