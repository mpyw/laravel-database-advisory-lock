<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use LogicException;
use Symfony\Component\Process\Process;

trait AcquiresLockInSeparateProcesses
{
    private static function lockAsync(string $driver, string $key, int $sleep): Process
    {
        if ($driver === 'mysql') {
            return self::lockMysqlAsync($key, $sleep);
        }
        if ($driver === 'mariadb') {
            return self::lockMariadbAsync($key, $sleep);
        }
        if ($driver === 'pgsql') {
            return self::lockPostgresAsync($key, $sleep);
        }

        throw new LogicException('Unsupported driver');
    }

    private static function lockMysqlAsync(string $key, int $sleep, string $name = 'mysql'): Process
    {
        $host = config("database.connections.{$name}.host");
        assert(is_string($host));

        $port = config("database.connections.{$name}.port");
        assert(is_scalar($port));

        // == is intentionally used instead of ===.
        // up to PHP 8.0, emulation mode on MySQL affects whether the return type is stringified or not.
        $proc = new Process([PHP_BINARY, '-r',
            <<<EOD
            \$pdo = new PDO('mysql:host={$host};port={$port};dbname=testing', 'testing', 'testing');
            \$result = \$pdo->query("SELECT GET_LOCK('{$key}', 0)")->fetchColumn();
            sleep({$sleep});
            exit(\$result == 1 ? 0 : 1);
            EOD,
        ]);
        $proc->start();

        return $proc;
    }

    private static function lockMariadbAsync(string $key, int $sleep): Process
    {
        return self::lockMysqlAsync($key, $sleep, 'mariadb');
    }

    private static function lockPostgresAsync(string $key, int $sleep): Process
    {
        $host = config('database.connections.pgsql.host');
        assert(is_string($host));

        $port = config('database.connections.pgsql.port');
        assert(is_scalar($port));

        $proc = new Process([PHP_BINARY, '-r',
            <<<EOD
            \$pdo = new PDO('pgsql:host={$host};port={$port};dbname=testing', 'testing', 'testing');
            \$result = \$pdo->query("SELECT pg_try_advisory_lock(hashtext('{$key}'))")->fetchColumn();
            sleep({$sleep});
            exit(\$result ? 0 : 1);
            EOD,
        ]);
        $proc->start();

        return $proc;
    }
}
