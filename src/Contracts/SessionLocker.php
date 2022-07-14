<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

/**
 * interface SessionLocker
 *
 * Session-level locker.
 * Acquired locks must be explicitly released or connection must be disconnected.
 */
interface SessionLocker
{
    /**
     * Invoke $callback under the acquired lock then release it.
     * QueryException may be thrown on connection-level errors.
     *
     * @phpstan-template T
     * @phpstan-param callable(ConnectionInterface): T $callback
     * @phpstan-return T
     *
     * @psalm-template T
     * @psalm-param callable(ConnectionInterface): T $callback
     * @psalm-return T
     *
     * @param int $timeout Time to wait before acquiring a lock. This is NOT the expiry of the lock.
     *
     * @throws LockFailedException
     * @throws QueryException
     */
    public function withLocking(string $key, callable $callback, int $timeout = 0): mixed;

    /**
     * Attempts to acquire a lock or returns NULL if failed.
     * QueryException may be thrown on connection-level errors.
     *
     * @throws QueryException
     */
    public function tryLock(string $key, int $timeout = 0): ?SessionLock;

    /**
     * Attempts to acquire a lock or throw LockFailedException if failed.
     * QueryException may be thrown on connection-level errors.
     *
     * @throws LockFailedException
     * @throws QueryException
     */
    public function lockOrFail(string $key, int $timeout = 0): SessionLock;

    /**
     * Indicates whether any session-level lock remains.
     */
    public function hasAny(): bool;
}
