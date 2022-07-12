<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Connections;

use Illuminate\Database\MySqlConnection as BaseMySqlConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\AdvisoryLocks;

class MySqlConnection extends BaseMySqlConnection
{
    use AdvisoryLocks;
}
