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

    public function connections(): array
    {
        return ['postgres' => ['pgsql'], 'mysql' => ['mysql']];
    }

    /**
     * @dataProvider connections
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
     * @dataProvider connections
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
     * @dataProvider connections
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
     * @dataProvider connections
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

    public function testMysqlHashing(): void
    {
        $key = str_repeat('a', 65);
        $passed = false;

        DB::connection('mysql')
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

    public function testMysqlHashingMultibyte(): void
    {
        $key = str_repeat('あ', 65);
        $passed = false;

        DB::connection('mysql')
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
     * @dataProvider connections
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
     * @dataProvider connections
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
     * @dataProvider connections
     */
    public function testInfiniteTimeoutSuccess(string $name): void
    {
        $proc = self::lockAsync($name, 'foo', 2);
        sleep(1);

        try {
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
