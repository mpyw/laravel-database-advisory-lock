<?php

declare(strict_types=1);

namespace Illuminate\Database
{
    use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockerFactory;

    if (false) {
        interface ConnectionInterface
        {
            public function advisoryLocker(): LockerFactory;
        }

        class Connection implements ConnectionInterface
        {
            public function advisoryLocker(): LockerFactory
            {
            }
        }
    }
}

namespace Illuminate\Support\Facades
{
    use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockerFactory;

    if (false) {
        class DB extends Facade
        {
            public static function advisoryLocker(): LockerFactory
            {
            }
        }
    }
}
