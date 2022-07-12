<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

interface LockerFactory
{
    public function forTransaction(): TransactionAwareLocker;

    public function persistent(): PersistentLocker;
}
