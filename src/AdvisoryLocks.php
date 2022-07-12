<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockerFactory as FactoryContract;

trait AdvisoryLocks
{
    public ?FactoryContract $advisoryLocker = null;

    public function advisoryLocker(): FactoryContract
    {
        assert($this instanceof Connection);

        return $this->advisoryLocker ??= new LockerFactory($this);
    }

    /**
     * @param  string         $query
     * @param  array          $bindings
     * @throws QueryException
     */
    protected function handleQueryException(QueryException $e, $query, $bindings, Closure $callback)
    {
        assert($this instanceof Connection);

        // Don't try again if there are session-level locks
        if ($this->transactionLevel() > 0 || $this->advisoryLocker()->forSession()->hasAny()) {
            throw $e;
        }

        return $this->tryAgainIfCausedByLostConnection(
            $e,
            $query,
            $bindings,
            $callback,
        );
    }
}
