<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\InvalidTransactionLevelException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\UnsupportedDriverException;
use Throwable;

class TransactionLockerTest extends TestCase
{
    use AcquiresLockInSeparateProcesses;

    public function connections(): array
    {
        return ['postgres' => ['pgsql']];
    }

    /**
     * @dataProvider connections
     * @throws Throwable
     */
    public function testDifferentKeysOnDifferentConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)->transaction(function (ConnectionInterface $conn) use ($name, &$passed): void {
            $conn
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            DB::connection("{$name}2")->transaction(function (ConnectionInterface $conn): void {
                $conn
                    ->advisoryLocker()
                    ->forTransaction()
                    ->lockOrFail('bar');
            });

            $passed = true;
        });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connections
     * @throws Throwable
     */
    public function testSameKeysOnDifferentConnections(string $name): void
    {
        DB::connection($name)->transaction(function (ConnectionInterface $conn) use ($name): void {
            $conn
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            $this->expectException(LockFailedException::class);
            $this->expectExceptionMessage('Failed to acquire lock: foo');

            DB::connection("{$name}2")->transaction(function (ConnectionInterface $conn): void {
                $conn
                    ->advisoryLocker()
                    ->forTransaction()
                    ->lockOrFail('foo');
            });
        });

        $this->fail();
    }

    /**
     * @dataProvider connections
     * @throws Throwable
     */
    public function testDifferentKeysOnSameConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)->transaction(function (ConnectionInterface $conn) use (&$passed): void {
            $conn
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            $conn
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('bar');

            $passed = true;
        });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connections
     * @throws Throwable
     */
    public function testSameKeysOnSameConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)->transaction(function (ConnectionInterface $conn) use (&$passed): void {
            $conn
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            $conn
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            $passed = true;
        });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connections
     * @throws Throwable
     */
    public function testWithoutTransactions(string $name): void
    {
        $this->expectException(InvalidTransactionLevelException::class);
        $this->expectExceptionMessage('There are no transactions');

        DB::connection($name)
            ->advisoryLocker()
            ->forTransaction()
            ->lockOrFail('foo');
    }

    /**
     * @throws Throwable
     */
    public function testFinitePostgresTimeoutInvalid(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('Positive timeout is not supported');

        DB::connection('pgsql')->transaction(function (ConnectionInterface $conn): void {
            $conn->advisoryLocker()->forTransaction()->lockOrFail('foo', 1);
        });
    }

    /**
     * @throws Throwable
     */
    public function testInfinitePostgresTimeoutSuccess(): void
    {
        $proc = self::lockPostgresAsync('foo', 2);
        sleep(1);

        try {
            $result = DB::connection('pgsql')->transaction(function (ConnectionInterface $conn) {
                return $conn->advisoryLocker()->forTransaction()->tryLock('foo', -1);
            });

            $this->assertSame(0, $proc->wait());
            $this->assertTrue($result);
        } finally {
            $proc->wait();
        }
    }
}
