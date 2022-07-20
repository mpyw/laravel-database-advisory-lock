<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Symfony\Component\Process\Process;

trait AcquiresLockInSeparateProcesses
{
    private static function lockMysqlAsync(string $key, int $sleep): Process
    {
        $host = config('database.connections.mysql.host');
        assert(is_string($host));

        // == is intentionally used instead of ===.
        // up to PHP 8.0, emulation mode on MySQL affects whether the return type is stringified or not.
        $proc = new Process([PHP_BINARY, '-r',
            <<<EOD
            \$pdo = new PDO('mysql:host={$host};dbname=testing', 'testing', 'testing');
            \$result = \$pdo->query("SELECT GET_LOCK('{$key}', 0)")->fetchColumn();
            sleep({$sleep});
            exit(\$result == 1 ? 0 : 1);
            EOD,
        ]);
        $proc->start();

        return $proc;
    }

    private static function lockPostgresAsync(string $key, int $sleep): Process
    {
        $host = config('database.connections.pgsql.host');
        assert(is_string($host));

        $proc = new Process([PHP_BINARY, '-r',
            <<<EOD
            \$pdo = new PDO('pgsql:host={$host};dbname=testing', 'testing', 'testing');
            \$result = \$pdo->query("SELECT pg_try_advisory_lock(hashtext('{$key}'))")->fetchColumn();
            sleep({$sleep});
            exit(\$result ? 0 : 1);
            EOD,
        ]);
        $proc->start();

        return $proc;
    }
}
