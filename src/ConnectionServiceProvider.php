<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Mpyw\LaravelDatabaseAdvisoryLock\Connections\MySqlConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Connections\PostgresConnection;

final class ConnectionServiceProvider extends ServiceProvider
{
    /**
     * You can optionally register these default connection implementations.
     */
    public function register(): void
    {
        Connection::resolverFor('mysql', static fn (...$args) => new MySqlConnection(...$args));
        Connection::resolverFor('pgsql', static fn (...$args) => new PostgresConnection(...$args));
    }
}
