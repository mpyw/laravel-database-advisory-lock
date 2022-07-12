<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Support\Facades\DB;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockConflictException;
use Throwable;

class TransactionAwareLockerTest extends TestCase
{
    protected function connections(): array
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

        DB::connection($name)->transaction(function () use ($name, &$passed): void {
            DB::connection($name)
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            DB::connection("{$name}2")->transaction(function () use ($name): void {
                DB::connection("{$name}2")
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
        DB::connection($name)->transaction(function () use ($name, &$passed): void {
            DB::connection($name)
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            $this->expectException(LockConflictException::class);
            $this->expectExceptionMessage('Failed to acquire lock: foo');

            DB::connection("{$name}2")->transaction(function () use ($name): void {
                DB::connection("{$name}2")
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

        DB::connection($name)->transaction(function () use ($name, &$passed): void {
            DB::connection($name)
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            DB::connection($name)
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

        DB::connection($name)->transaction(function () use ($name, &$passed): void {
            DB::connection($name)
                ->advisoryLocker()
                ->forTransaction()
                ->lockOrFail('foo');

            DB::connection($name)
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
    public function testInstantUnlockingWithoutTransaction(string $name): void
    {
        DB::connection($name)
            ->advisoryLocker()
            ->forTransaction()
            ->lockOrFail('foo');

        DB::connection("{$name}2")
            ->advisoryLocker()
            ->forTransaction()
            ->lockOrFail('foo');

        $this->assertTrue(true);
    }
}
