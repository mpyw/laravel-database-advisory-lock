<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Support\Facades\DB;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockConflictException;

class PersistentLockerTest extends TestCase
{
    protected function connections(): array
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
            ->persistent()
            ->withLocking('foo', function () use ($name, &$passed): void {
                DB::connection("{$name}2")
                    ->advisoryLocker()
                    ->persistent()
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
            ->persistent()
            ->withLocking('foo', function () use ($name, &$passed): void {
                $this->expectException(LockConflictException::class);
                $this->expectExceptionMessage('Failed to acquire lock: foo');

                DB::connection("{$name}2")
                    ->advisoryLocker()
                    ->persistent()
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
            ->persistent()
            ->withLocking('foo', function () use ($name, &$passed): void {
                DB::connection($name)
                    ->advisoryLocker()
                    ->persistent()
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
            ->persistent()
            ->withLocking('foo', function () use ($name, &$passed): void {
                DB::connection($name)
                    ->advisoryLocker()
                    ->persistent()
                    ->withLocking('foo', function () use (&$passed): void {
                        $passed = true;
                    });
            });

        $this->assertTrue($passed);
    }

    /**
     * @dataProvider connections
     */
    public function testMySqlTimeout(string $name): void
    {
        if ($name !== 'mysql') {
            $this->markTestSkipped();
        }

        $passed = false;

        DB::connection($name)
            ->advisoryLocker()
            ->persistent()
            ->withLocking('foo', function () use ($name, &$passed): void {
                DB::connection($name)
                    ->advisoryLocker()
                    ->persistent()
                    ->withLocking('foo', function () use (&$passed): void {
                        $passed = true;
                    });
            });

        $this->assertTrue($passed);
    }
}
