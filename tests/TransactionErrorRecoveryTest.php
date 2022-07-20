<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionErrorRecoveryTest extends TableTestCase
{
    /**
     * @throws Throwable
     */
    public function testWithoutTransactions(): void
    {
        $passed = false;

        $conn = DB::connection('pgsql');
        assert($conn instanceof Connection);
        $conn->enableQueryLog();

        $conn
            ->advisoryLocker()
            ->forSession()
            ->withLocking('foo', function (ConnectionInterface $conn) use (&$passed): void {
                $this->assertSame(0, $conn->transactionLevel());
                $conn->insert('insert into users(id) values(1)');

                try {
                    // The following statement triggers an error
                    $conn->insert('insert into users(id) values(1)');
                } catch (QueryException) {
                }
                // The following statement is valid because there are no transactions
                $conn->insert('insert into users(id) values(2)');
                $passed = true;
            });

        $this->assertTrue($passed);
        $this->assertSame([
            'SELECT pg_try_advisory_lock(hashtext(?))',
            'insert into users(id) values(1)',
            'insert into users(id) values(2)',
            'SELECT pg_advisory_unlock(hashtext(?))',
        ], array_column($conn->getQueryLog(), 'query'));

        $this->assertNotNull(
            DB::connection('pgsql2')
                ->advisoryLocker()
                ->forSession()
                ->tryLock('foo'),
        );
    }

    /**
     * @throws Throwable
     */
    public function testWithLockingRollbacksToSavepoint(): void
    {
        $passed = false;

        $conn = DB::connection('pgsql');
        assert($conn instanceof Connection);
        $conn->enableQueryLog();

        $conn->transaction(function (ConnectionInterface $conn) use (&$passed): void {
            $this->assertSame(1, $conn->transactionLevel());
            $conn->insert('insert into users(id) values(1)');

            try {
                $conn
                    ->advisoryLocker()
                    ->forSession()
                    ->withLocking('foo', function (ConnectionInterface $conn): void {
                        // The level is 2 because savepoint is automatically created
                        $this->assertSame(2, $conn->transactionLevel());

                        // The following statement triggers an error
                        $conn->insert('insert into users(id) values(1)');
                        $this->fail();
                    });
                // @phpstan-ignore-next-line
                $this->fail();
            } catch (QueryException) {
            }
            // The following statement is valid because it is rolled back to the savepoint
            $this->assertSame(1, $conn->transactionLevel());
            $conn->insert('insert into users(id) values(2)');
            $passed = true;
        });

        $this->assertTrue($passed);
        $this->assertSame([
            'insert into users(id) values(1)',
            'SELECT pg_try_advisory_lock(hashtext(?))',
            'SELECT pg_advisory_unlock(hashtext(?))',
            'insert into users(id) values(2)',
        ], array_column($conn->getQueryLog(), 'query'));

        $this->assertNotNull(
            DB::connection('pgsql2')
                ->advisoryLocker()
                ->forSession()
                ->tryLock('foo'),
        );
    }

    /**
     * @throws Throwable
     */
    public function testDestructorReleasesLocksAfterTransactionTerminated(): void
    {
        $conn = DB::connection('pgsql');
        assert($conn instanceof Connection);
        $conn->enableQueryLog();

        try {
            $conn->transaction(function (ConnectionInterface $conn): void {
                // lockOrFail() doesn't create any savepoints
                $this->assertSame(1, $conn->transactionLevel());

                /** @noinspection PhpUnusedLocalVariableInspection */
                $lock = $conn->advisoryLocker()->forSession()->lockOrFail('foo');
                $this->assertSame(1, $conn->transactionLevel());

                $conn->insert('insert into users(id) values(1)');

                try {
                    // The following statement triggers an error
                    $conn->insert('insert into users(id) values(1)');
                } catch (QueryException) {
                }
                // The following statement is invalid [*]
                $conn->insert('insert into users(id) values(2)');
                $this->fail();
            });
            $this->fail();
        } catch (QueryException $e) {
            // Thrown from [*]
            $this->assertSame(
                'SQLSTATE[25P02]: In failed sql transaction: 7 ERROR:  '
                . 'current transaction is aborted, commands ignored until end of transaction block '
                . (
                    version_compare($this->app->version(), '10.x-dev', '>=')
                        ? '(Connection: pgsql, SQL: insert into users(id) values(2))'
                        : '(SQL: insert into users(id) values(2))'
                ),
                $e->getMessage(),
            );
        }

        $this->assertSame([
            'SELECT pg_try_advisory_lock(hashtext(?))',
            'insert into users(id) values(1)',
            'SELECT pg_advisory_unlock(hashtext(?))',
        ], array_column($conn->getQueryLog(), 'query'));

        $this->assertNotNull(
            DB::connection('pgsql2')
                ->advisoryLocker()
                ->forSession()
                ->tryLock('foo'),
        );
    }
}
