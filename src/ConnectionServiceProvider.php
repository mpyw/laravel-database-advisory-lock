<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Mpyw\LaravelDatabaseAdvisoryLock\Connections\MySqlConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Connections\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Connections\SqlServerConnection;

final class ConnectionServiceProvider extends ServiceProvider
{
    /**
     * You can optionally register these default connection implementations.
     */
    public function register(): void
    {
        Connection::resolverFor('mysql', fn (...$args) => new MySqlConnection(...$args));
        Connection::resolverFor('pgsql', fn (...$args) => new PostgresConnection(...$args));
        Connection::resolverFor('sqlsrv', fn (...$args) => new SqlServerConnection(...$args));
    }
}
