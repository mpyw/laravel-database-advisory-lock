<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Utilities;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use LogicException;

use function preg_replace;
use function str_starts_with;

/**
 * class PostgresTryLockLoopEmulator
 *
 * @internal
 */
final class PostgresTryLockLoopEmulator
{
    private Connection $connection;

    public function __construct(
        ConnectionInterface $connection,
    ) {
        if (!$connection instanceof Connection) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Procedure features are not available.');
            // @codeCoverageIgnoreEnd
        }

        $this->connection = $connection;
    }

    /**
     * Perform a time-limited lock acquisition.
     *
     * @phpstan-param positive-int $timeout
     * @throws QueryException
     */
    public function performTryLockLoop(string $key, int $timeout, bool $forTransaction = false): bool
    {
        try {
            // Binding parameters to procedures is only allowed when PDOStatement emulation is enabled.
            PDOStatementEmulator::emulated(
                $this->connection->getPdo(),
                fn () => $this->performRawTryLockLoop($key, $timeout, $forTransaction),
            );
            // @codeCoverageIgnoreStart
            throw new LogicException('Unreachable here');
            // @codeCoverageIgnoreEnd
        } catch (QueryException $e) {
            // Handle user level exceptions
            if ($e->getCode() === 'P0001') {
                $prefix = 'ERROR:  LaravelDatabaseAdvisoryLock';
                $message = (string)($e->errorInfo[2] ?? '');
                if (str_starts_with($message, "{$prefix}: Lock acquired successfully")) {
                    return true;
                }
                if (str_starts_with($message, "{$prefix}: Lock timeout")) {
                    return false;
                }
            }

            throw $e;
        }
    }

    /**
     * Generates SQL to emulate time-limited lock acquisition.
     * This query will always throw QueryException.
     *
     * @phpstan-param positive-int $timeout
     * @throws QueryException
     */
    public function performRawTryLockLoop(string $key, int $timeout, bool $forTransaction): void
    {
        $this->connection->select($this->sql($timeout, $forTransaction), [$key]);
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
            DO $$
                DECLARE
                    result boolean;
                    start timestamp with time zone;
                    now timestamp with time zone;
                BEGIN
                    start := clock_timestamp();
                    LOOP
                        SELECT pg_try_advisory{$suffix}_lock(hashtext(?)) INTO result;
                        IF result THEN
                            RAISE 'LaravelDatabaseAdvisoryLock: Lock acquired successfully';
                        END IF;
                        now := clock_timestamp();
                        IF now - start > interval '{$timeout} seconds' THEN
                            RAISE 'LaravelDatabaseAdvisoryLock: Lock timeout';
                        END IF;
                        PERFORM pg_sleep(0.5);
                    END LOOP;
                END
            $$;
        EOD;

        return (string)preg_replace('/\s++/', ' ', $sql);
    }
}
