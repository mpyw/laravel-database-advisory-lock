<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Mpyw\LaravelDatabaseAdvisoryLock\AdvisoryLockServiceProvider;
use Mpyw\LaravelDatabaseAdvisoryLock\ConnectionServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AdvisoryLockServiceProvider::class,
            ConnectionServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config(['database.connections.mariadb' => config('database.connections.mysql')]);
        config([
            'database.connections.pgsql.host' => env('PG_HOST', 'postgres'),
            'database.connections.pgsql.port' => env('PG_PORT', '5432'),
            'database.connections.pgsql.database' => 'testing',
            'database.connections.pgsql.username' => 'testing',
            'database.connections.pgsql.password' => 'testing',
            'database.connections.mysql.host' => env('MY_HOST', 'mysql'),
            'database.connections.mysql.port' => env('MY_PORT', '3306'),
            'database.connections.mysql.database' => 'testing',
            'database.connections.mysql.username' => 'testing',
            'database.connections.mysql.password' => 'testing',
            'database.connections.mariadb.host' => env('MA_HOST', 'mariadb'),
            'database.connections.mariadb.port' => env('MA_PORT', '3306'),
            'database.connections.mariadb.database' => 'testing',
            'database.connections.mariadb.username' => 'testing',
            'database.connections.mariadb.password' => 'testing',
        ]);
        config([
            'database.connections.mysql2' => config('database.connections.mysql'),
            'database.connections.mariadb2' => config('database.connections.mariadb'),
            'database.connections.pgsql2' => config('database.connections.pgsql'),
        ]);
    }

    public function connectionsAll(): array
    {
        return ['postgres' => ['pgsql'], 'mysql' => ['mysql'], 'mariadb' => ['mariadb']];
    }

    public function connectionsMysql(): array
    {
        return ['mysql' => ['mysql']];
    }

    public function connectionsMysqlLike(): array
    {
        return ['mysql' => ['mysql'], 'mariadb' => ['mariadb']];
    }

    public function connectionsPostgres(): array
    {
        return ['postgres' => ['pgsql']];
    }
}
