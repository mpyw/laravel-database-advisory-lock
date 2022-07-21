<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\InvalidTransactionLevelException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Throwable;

class TransactionLockerTest extends TestCase
{
    use AcquiresLockInSeparateProcesses;

    /**
     * @dataProvider connectionsPostgres
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
     * @dataProvider connectionsPostgres
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
     * @dataProvider connectionsPostgres
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
     * @dataProvider connectionsPostgres
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
     * @dataProvider connectionsPostgres
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
     * @dataProvider connectionsPostgres
     * @throws Throwable
     */
    public function testFiniteTimeoutSuccess(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 2);
        sleep(1);

        try {
            $result = DB::connection($name)->transaction(function (ConnectionInterface $conn) {
                return $conn->advisoryLocker()->forTransaction()->tryLock('foo', 3);
            });

            $this->assertSame(0, $proc->wait());
            $this->assertTrue($result);
        } finally {
            $proc->wait();
        }
    }

    /**
     * @dataProvider connectionsPostgres
     * @throws Throwable
     */
    public function testFinitePostgresTimeoutSuccessConsecutive(string $name): void
    {
        $proc1 = self::lockAsync($name, 'foo', 5);
        $proc2 = self::lockAsync($name, 'baz', 5);
        sleep(1);

        try {
            $result = DB::connection($name)->transaction(function (ConnectionInterface $conn) {
                return [
                    $conn->advisoryLocker()->forTransaction()->tryLock('foo', 1),
                    $conn->advisoryLocker()->forTransaction()->tryLock('bar', 1),
                    $conn->advisoryLocker()->forTransaction()->tryLock('baz', 1),
                    $conn->advisoryLocker()->forTransaction()->tryLock('qux', 1),
                ];
            });
            $this->assertSame(0, $proc1->wait());
            $this->assertSame(0, $proc2->wait());
            $this->assertSame([false, true, false, true], $result);
        } finally {
            $proc1->wait();
            $proc2->wait();
        }
    }

    /**
     * @dataProvider connectionsPostgres
     * @throws Throwable
     */
    public function testFinitePostgresTimeoutExceeded(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 3);
        sleep(1);

        try {
            $result = DB::connection($name)->transaction(function (ConnectionInterface $conn) {
                return $conn->advisoryLocker()->forTransaction()->tryLock('foo', 1);
            });

            $this->assertSame(0, $proc->wait());
            $this->assertFalse($result);
        } finally {
            $proc->wait();
        }
    }

    /**
     * @dataProvider connectionsPostgres
     * @throws Throwable
     */
    public function testInfinitePostgresTimeoutSuccess(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 2);
        sleep(1);

        try {
            $result = DB::connection($name)->transaction(function (ConnectionInterface $conn) {
                return $conn->advisoryLocker()->forTransaction()->tryLock('foo', -1);
            });

            $this->assertSame(0, $proc->wait());
            $this->assertTrue($result);
        } finally {
            $proc->wait();
        }
    }
}
