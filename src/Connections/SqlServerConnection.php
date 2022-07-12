<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Connections;

use Illuminate\Database\SqlServerConnection as BaseSqlServerConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\AdvisoryLocks;

class SqlServerConnection extends BaseSqlServerConnection
{
    use AdvisoryLocks;
}
