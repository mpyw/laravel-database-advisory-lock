<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\QueryException;

interface SessionLocker
{
    /**
     * @phpstan-template T
     * @phpstan-param callable(): T $callback
     * @phpstan-return T
     *
     * @psalm-template T
     * @psalm-param callable(): T $callback
     * @psalm-return T
     *
     * @throws LockFailedException
     * @throws QueryException
     */
    public function withLocking(string $key, callable $callback, int $timeout = 0): mixed;

    /**
     * @throws QueryException
     */
    public function tryLock(string $key, int $timeout = 0): ?SessionLock;

    /**
     * @throws LockFailedException
     * @throws QueryException
     */
    public function lockOrFail(string $key, int $timeout = 0): SessionLock;

    public function hasAny(): bool;
}