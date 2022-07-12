<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\QueryException;

interface PersistentLock
{
    /**
     * @throws QueryException
     */
    public function release(): bool;

    /**
     * @throws QueryException
     */
    public function __destruct();
}
