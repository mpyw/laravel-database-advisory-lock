<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Utilities;

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\QueryException;

use function preg_replace;

/**
 * class PostgresTimeoutEmulator
 *
 * @internal
 */
final class PostgresTimeoutEmulator
{
    public function __construct(
        private PostgresConnection $connection,
    ) {}

    /**
     * Perform a time-limited lock acquisition.
     *
     * @phpstan-param positive-int $timeout
     * @throws QueryException
     */
    public function performWithTimeout(string $key, int $timeout, bool $forTransaction = false): bool
    {
        // Binding parameters to procedures is only allowed when PDOStatement emulation is enabled.
        return PDOStatementEmulator::emulated(
            $this->connection->getPdo(),
            fn () => (bool)(new Selector($this->connection))
                ->select($this->sql($timeout, $forTransaction), [$key]),
        );
    }

    /**
     * Generates SQL to emulate time-limited lock acquisition.
     *
     * @phpstan-param positive-int $timeout
     */
    public function sql(int $timeout, bool $forTransaction): string
    {
        $suffix = $forTransaction ? '_xact' : '';
        $modifier = $forTransaction ? 'LOCAL' : 'SESSION';

        $sql = <<<EOD
            CREATE OR REPLACE FUNCTION
                pg_temp.laravel_pg_try_advisory{$suffix}_lock_timeout(key text, timeout text)
            RETURNS boolean
            SET lock_timeout FROM CURRENT
            AS $$
                BEGIN
                    EXECUTE format('SET {$modifier} lock_timeout TO %L;', timeout);
                    PERFORM pg_advisory{$suffix}_lock(hashtext(key));
                    RETURN true;
                EXCEPTION
                    WHEN lock_not_available OR deadlock_detected THEN RETURN false;
                END
            $$
            LANGUAGE plpgsql;
            SELECT pg_temp.laravel_pg_try_advisory{$suffix}_lock_timeout(?, '{$timeout}s');
        EOD;

        return (string)preg_replace('/\s++/', ' ', $sql);
    }
}
