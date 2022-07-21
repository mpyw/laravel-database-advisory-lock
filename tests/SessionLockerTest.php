<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockFailedException;
use Mpyw\LaravelDatabaseAdvisoryLock\Utilities\Selector;

class SessionLockerTest extends TestCase
{
    use AcquiresLockInSeparateProcesses;

    /**
     * @dataProvider connectionsAll
     */
    public function testDifferentKeysOnDifferentConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)
            ->advisoryLocker()
            ->forSession()
            ->withLocking('foo', function () use ($name, &$passed): void {
                DB::connection("{$name}2")
                    ->advisoryLocker()
                    ->forSession()
                    ->withLocking('bar', function () use (&$passed): void {
                        $passed = true;
                    });
            });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connectionsAll
     */
    public function testSameKeysOnDifferentConnections(string $name): void
    {
        DB::connection($name)
            ->advisoryLocker()
            ->forSession()
            ->withLocking('foo', function () use ($name, &$passed): void {
                $this->expectException(LockFailedException::class);
                $this->expectExceptionMessage('Failed to acquire lock: foo');

                DB::connection("{$name}2")
                    ->advisoryLocker()
                    ->forSession()
                    ->withLocking('foo', function () use (&$passed): void {
                        $passed = true;
                    });
            });

        $this->fail();
    }

    /**
     * @dataProvider connectionsAll
     */
    public function testDifferentKeysOnSameConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)
            ->advisoryLocker()
            ->forSession()
            ->withLocking('foo', function (ConnectionInterface $conn) use (&$passed): void {
                $conn
                    ->advisoryLocker()
                    ->forSession()
                    ->withLocking('bar', function () use (&$passed): void {
                        $passed = true;
                    });
            });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connectionsAll
     */
    public function testSameKeysOnSameConnections(string $name): void
    {
        $passed = false;

        DB::connection($name)
            ->advisoryLocker()
            ->forSession()
            ->withLocking('foo', function (ConnectionInterface $conn) use (&$passed): void {
                $conn
                    ->advisoryLocker()
                    ->forSession()
                    ->withLocking('foo', function () use (&$passed): void {
                        $passed = true;
                    });
            });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connectionsMysqlLike
     */
    public function testMysqlHashing(string $name): void
    {
        $key = str_repeat('a', 65);
        $passed = false;

        DB::connection($name)
            ->advisoryLocker()
            ->forSession()
            ->withLocking($key, function (ConnectionInterface $conn) use ($key, &$passed): void {
                $this->assertTrue(
                    (bool)(new Selector($conn))
                        ->select(
                            'SELECT IS_USED_LOCK(?)',
                            [substr($key, 0, 64 - 40) . sha1($key)],
                        ),
                );
                $passed = true;
            });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connectionsMysqlLike
     */
    public function testMysqlHashingMultibyte(string $name): void
    {
        $key = str_repeat('あ', 65);
        $passed = false;

        DB::connection($name)
            ->advisoryLocker()
            ->forSession()
            ->withLocking($key, function (ConnectionInterface $conn) use ($key, &$passed): void {
                $this->assertTrue(
                    (bool)(new Selector($conn))
                        ->select(
                            'SELECT IS_USED_LOCK(?)',
                            [mb_substr($key, 0, 64 - 40) . sha1($key)],
                        ),
                );
                $passed = true;
            });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connectionsAll
     */
    public function testFiniteTimeoutSuccess(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 2);
        sleep(1);

        try {
            $result = DB::connection($name)
                ->advisoryLocker()
                ->forSession()
                ->tryLock('foo', 3);

            $this->assertSame(0, $proc->wait());
            $this->assertNotNull($result);
        } finally {
            $proc->wait();
        }
    }

    /**
     * @dataProvider connectionsAll
     */
    public function testFiniteTimeoutSuccessConsecutive(string $name): void
    {
        $proc1 = self::lockAsync($name, 'foo', 5);
        $proc2 = self::lockAsync($name, 'baz', 5);
        sleep(1);

        try {
            $conn = DB::connection($name);
            $results = [
                $conn->advisoryLocker()->forSession()->tryLock('foo', 1),
                $conn->advisoryLocker()->forSession()->tryLock('bar', 1),
                $conn->advisoryLocker()->forSession()->tryLock('baz', 1),
                $conn->advisoryLocker()->forSession()->tryLock('qux', 1),
            ];
            $result_booleans = array_map(fn ($result) => $result !== null, $results);
            $this->assertSame(0, $proc1->wait());
            $this->assertSame(0, $proc2->wait());
            $this->assertSame([false, true, false, true], $result_booleans);
        } finally {
            $proc1->wait();
            $proc2->wait();
        }
    }

    /**
     * @dataProvider connectionsAll
     */
    public function testFiniteTimeoutExceeded(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 3);
        sleep(1);

        try {
            $result = DB::connection($name)
                ->advisoryLocker()
                ->forSession()
                ->tryLock('foo', 1);

            $this->assertSame(0, $proc->wait());
            $this->assertNull($result);
        } finally {
            $proc->wait();
        }
    }

    /**
     * @dataProvider connectionsMysql
     * @dataProvider connectionsPostgres
     */
    public function testInfiniteTimeoutSuccess(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 2);
        sleep(1);

        try {
            // MariaDB does not accept negative values
            $result = DB::connection($name)
                ->advisoryLocker()
                ->forSession()
                ->tryLock('foo', -1);

            $this->assertSame(0, $proc->wait());
            $this->assertNotNull($result);
        } finally {
            $proc->wait();
        }
    }
}
