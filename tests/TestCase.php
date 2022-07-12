<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Mpyw\LaravelDatabaseAdvisoryLock\ConnectionServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ConnectionServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config([
            'database.connections.pgsql.host' => env('PG_HOST', 'postgres'),
            'database.connections.pgsql.database' => 'testing',
            'database.connections.pgsql.username' => 'testing',
            'database.connections.pgsql.password' => 'testing',
            'database.connections.mysql.host' => env('MY_HOST', 'mysql'),
            'database.connections.mysql.database' => 'testing',
            'database.connections.mysql.username' => 'testing',
            'database.connections.mysql.password' => 'testing',
        ]);
        config([
            'database.connections.mysql2' => config('database.connections.mysql'),
            'database.connections.pgsql2' => config('database.connections.pgsql'),
        ]);
    }
}
