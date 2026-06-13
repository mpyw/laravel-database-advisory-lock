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
        config(['database.connections.mariadb' => array_merge(
            (array)config('database.connections.mysql'),
            ['driver' => 'mariadb'],
        )]);
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

    /**
     * Build the trailing "(...SQL: <sql>)" portion of a QueryException message
     * as produced by the running Laravel version. The format changed over time:
     *
     * - Laravel < 10:  (SQL: <sql>)
     * - Laravel >= 10: (Connection: <name>, SQL: <sql>)
     * - Laravel >= 13: (Connection: <name>, Host: <host>, Port: <port>, Database: <db>, SQL: <sql>)
     *
     * The host/port/database are read from the connection config so the
     * expectation stays exact regardless of the test environment.
     */
    protected function expectedQueryExceptionTail(string $sql, string $connection = 'pgsql'): string
    {
        $version = $this->app?->version() ?? '';

        if (version_compare($version, '10.x-dev', '<')) {
            return "(SQL: {$sql})";
        }

        if (version_compare($version, '13.x-dev', '>=')) {
            $host = config("database.connections.{$connection}.host");
            assert(is_string($host));

            $port = config("database.connections.{$connection}.port");
            assert(is_scalar($port));

            $database = config("database.connections.{$connection}.database");
            assert(is_string($database));

            return "(Connection: {$connection}, Host: {$host}, Port: {$port}, Database: {$database}, SQL: {$sql})";
        }

        return "(Connection: {$connection}, SQL: {$sql})";
    }
}
