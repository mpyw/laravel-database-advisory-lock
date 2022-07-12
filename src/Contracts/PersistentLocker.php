<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\QueryException;

interface PersistentLocker
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
     * @throws LockConflictException
     * @throws QueryException
     */
    public function withLocking(string $key, callable $callback, int $timeout = 0): mixed;

    /**
     * @throws QueryException
     */
    public function tryLock(string $key, int $timeout = 0): ?PersistentLock;

    /**
     * @throws LockConflictException
     * @throws QueryException
     */
    public function lockOrFail(string $key, int $timeout = 0): PersistentLock;

    public function hasAny(): bool;
}
