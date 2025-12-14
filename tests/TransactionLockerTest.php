<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\InvalidTransactionLevelException;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use PHPUnit\Framework\Attributes\DataProvider;

class TransactionLockerTest extends TestCase
{
    use AcquiresLockInSeparateProcesses;

    #[DataProvider('connectionsPostgres')]
    public function testDifferentKeysOnDifferentConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)->transaction(static function (ConnectionInterface $conn) use ($name, &$passed): void {
            $conn
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            DB::connection("{$name}2")->transaction(static function (ConnectionInterface $conn): void {
                $conn
                    ->advisoryLocker()
                    ->forTransaction()
                    ->lockOrFail('bar');
            });

            $passed = true;
        });

        $this->assertTrue($passed);
    }

    #[DataProvider('connectionsPostgres')]
    public function testSameKeysOnDifferentConnections(string $name): void
    {
        DB::connection($name)->transaction(function (ConnectionInterface $conn) use ($name): void {
            $conn
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            $this->expectException(LockFailedException::class);
            $this->expectExceptionMessage('Failed to acquire lock: foo');

            DB::connection("{$name}2")->transaction(static function (ConnectionInterface $conn): void {
                $conn
                    ->advisoryLocker()
                    ->forTransaction()
                    ->lockOrFail('foo');
            });
        });

        $this->fail();
    }

    #[DataProvider('connectionsPostgres')]
    public function testDifferentKeysOnSameConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)->transaction(static function (ConnectionInterface $conn) use (&$passed): void {
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

    #[DataProvider('connectionsPostgres')]
    public function testSameKeysOnSameConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)->transaction(static function (ConnectionInterface $conn) use (&$passed): void {
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

    #[DataProvider('connectionsPostgres')]
    public function testWithoutTransactions(string $name): void
    {
        $this->expectException(InvalidTransactionLevelException::class);
        $this->expectExceptionMessage('There are no transactions');

        DB::connection($name)
            ->advisoryLocker()
            ->forTransaction()
            ->lockOrFail('foo');
    }

    #[DataProvider('connectionsPostgres')]
    public function testFiniteTimeoutSuccess(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 2);
        sleep(1);

        try {
            $result = DB::connection($name)->transaction(static function (ConnectionInterface $conn) {
                return $conn->advisoryLocker()->forTransaction()->tryLock('foo', 3);
            });

            $this->assertSame(0, $proc->wait());
            $this->assertTrue($result);
        } finally {
            $proc->wait();
        }
    }

    #[DataProvider('connectionsPostgres')]
    public function testFinitePostgresTimeoutSuccessConsecutive(string $name): void
    {
        $proc1 = self::lockAsync($name, 'foo', 5);
        $proc2 = self::lockAsync($name, 'baz', 5);
        sleep(1);

        try {
            $result = DB::connection($name)->transaction(static function (ConnectionInterface $conn) {
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

    #[DataProvider('connectionsPostgres')]
    public function testFinitePostgresTimeoutExceeded(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 3);
        sleep(1);

        try {
            $result = DB::connection($name)->transaction(static function (ConnectionInterface $conn) {
                return $conn->advisoryLocker()->forTransaction()->tryLock('foo', 1);
            });

            $this->assertSame(0, $proc->wait());
            $this->assertFalse($result);
        } finally {
            $proc->wait();
        }
    }

    #[DataProvider('connectionsPostgres')]
    public function testInfinitePostgresTimeoutSuccess(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 2);
        sleep(1);

        try {
            $result = DB::connection($name)->transaction(static function (ConnectionInterface $conn) {
                return $conn->advisoryLocker()->forTransaction()->tryLock('foo', -1);
            });

            $this->assertSame(0, $proc->wait());
            $this->assertTrue($result);
        } finally {
            $proc->wait();
        }
    }
}
