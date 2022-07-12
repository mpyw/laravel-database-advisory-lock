<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

interface LockerFactory
{
    public function forTransaction(): TransactionLocker;

    public function forSession(): SessionLocker;
}
