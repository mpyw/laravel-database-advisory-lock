<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockerFactory as FactoryContract;

use function property_exists;

trait AdvisoryLocks
{
    public ?FactoryContract $advisoryLocker = null;

    /**
     * @param  string         $query
     * @param  array          $bindings
     * @throws QueryException
     */
    abstract protected function tryAgainIfCausedByLostConnection(
        QueryException $e,
        $query,
        $bindings,
        Closure $callback,
    );

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
        assert(property_exists($this, 'transactions'));

        // Don't try again if there are persistent locks
        if ((int)$this->transactions >= 1 || $this->advisoryLocker()->persistent()->hasAny()) {
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
