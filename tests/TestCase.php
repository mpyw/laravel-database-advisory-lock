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
            'database.connections.pgsql.host' => getenv('PG_HOST') ?: 'postgres',
            'database.connections.pgsql.port' => getenv('PG_PORT') ?: '5432',
            'database.connections.pgsql.database' => 'testing',
            'database.connections.pgsql.username' => 'testing',
            'database.connections.pgsql.password' => 'testing',
            'database.connections.mysql.host' => getenv('MY_HOST') ?: 'mysql',
            'database.connections.mysql.port' => getenv('MY_PORT') ?: '3306',
            'database.connections.mysql.database' => 'testing',
            'database.connections.mysql.username' => 'testing',
            'database.connections.mysql.password' => 'testing',
            'database.connections.mariadb.host' => getenv('MA_HOST') ?: 'mariadb',
            'database.connections.mariadb.port' => getenv('MA_PORT') ?: '3306',
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

    public static function connectionsAll(): array
    {
        return ['postgres' => ['pgsql'], 'mysql' => ['mysql'], 'mariadb' => ['mariadb']];
    }

    public static function connectionsMysql(): array
    {
        return ['mysql' => ['mysql']];
    }

    public static function connectionsMysqlLike(): array
    {
        return ['mysql' => ['mysql'], 'mariadb' => ['mariadb']];
    }

    public static function connectionsPostgres(): array
    {
        return ['postgres' => ['pgsql']];
    }
}
