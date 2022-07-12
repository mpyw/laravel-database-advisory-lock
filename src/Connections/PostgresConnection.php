<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Connections;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\AdvisoryLocks;

class PostgresConnection extends BasePostgresConnection
{
    use AdvisoryLocks;
}
