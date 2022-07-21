<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Utilities;

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\QueryException;

use function preg_replace;

/**
 * class PostgresTryLockLoopEmulator
 *
 * @internal
 */
final class PostgresTryLockLoopEmulator
{
    public function __construct(
        private PostgresConnection $connection,
    ) {
    }

    /**
     * Perform a time-limited lock acquisition.
     *
     * @phpstan-param positive-int $timeout
     * @throws QueryException
     */
    public function performTryLockLoop(string $key, int $timeout, bool $forTransaction = false): bool
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

        $sql = <<<EOD
            CREATE OR REPLACE FUNCTION
                pg_temp.laravel_pg_try_advisory{$suffix}_lock_timeout(key text, timeout interval)
            RETURNS boolean
            AS $$
                DECLARE
                    result boolean;
                    start timestamp with time zone;
                    now timestamp with time zone;
                BEGIN
                    start := clock_timestamp();
                    LOOP
                        SELECT pg_try_advisory{$suffix}_lock(hashtext(key)) INTO result;
                        IF result THEN
                            RETURN true;
                        END IF;
                        now := clock_timestamp();
                        IF now - start > timeout THEN
                            RETURN false;
                        END IF;
                        PERFORM pg_sleep(0.5);
                    END LOOP;
                END
            $$
            LANGUAGE plpgsql;
            SELECT pg_temp.laravel_pg_try_advisory{$suffix}_lock_timeout(?, interval '{$timeout} seconds');
        EOD;

        return (string)preg_replace('/\s++/', ' ', $sql);
    }
}
