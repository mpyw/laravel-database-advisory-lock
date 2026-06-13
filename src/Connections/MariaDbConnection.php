<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Connections;

use Illuminate\Database\MariaDbConnection as BaseMariaDbConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\AdvisoryLocks;

class MariaDbConnection extends BaseMariaDbConnection
{
    use AdvisoryLocks;
}
