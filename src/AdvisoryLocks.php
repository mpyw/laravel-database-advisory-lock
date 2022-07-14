<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockerFactory as FactoryContract;

/**
 * trait AdvisoryLocks
 *
 * Designed to be mixed in with the Connection classes.
 */
trait AdvisoryLocks
{
    public ?FactoryContract $advisoryLocker = null;

    /**
     * Create LockerFactory or return existing one.
     */
    public function advisoryLocker(): FactoryContract
    {
        assert($this instanceof Connection);

        return $this->advisoryLocker ??= new LockerFactory($this);
    }

    /**
     * Overrides the original implementation.
     *
     * @param  string         $query
     * @param  array          $bindings
     * @throws QueryException
     */
    protected function handleQueryException(QueryException $e, $query, $bindings, Closure $callback)
    {
        assert($this instanceof Connection);

        // Don't try again if there are session-level locks.
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
