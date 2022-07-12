<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SqlServerConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\PersistentLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\TransactionAwareLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\UnsupportedDriverException;

class LockerFactory implements Contracts\LockerFactory
{
    protected ?TransactionAwareLocker $transaction = null;
    protected ?PersistentLocker $persistent = null;

    public function __construct(
        protected Connection $connection,
    ) {
    }

    public function forTransaction(): TransactionAwareLocker
    {
        if ($this->connection instanceof PostgresConnection) {
            return $this->transaction ??= new PostgresTransactionAwareLocker($this->connection);
        }
        if ($this->connection instanceof SqlServerConnection) {
            return $this->transaction ??= new SqlServerTransactionAwareLocker($this->connection);
        }
        // @codeCoverageIgnoreStart
        throw new UnsupportedDriverException('TransactionAwareLocker is not supported');
        // @codeCoverageIgnoreEnd
    }

    public function persistent(): PersistentLocker
    {
        if ($this->connection instanceof MySqlConnection) {
            return $this->persistent ??= new MySqlPersistentLocker($this->connection);
        }
        if ($this->connection instanceof PostgresConnection) {
            return $this->persistent ??= new PostgresPersistentLocker($this->connection);
        }
        if ($this->connection instanceof SqlServerConnection) {
            return $this->persistent ??= new SqlServerPersistentLocker($this->connection);
        }
        // @codeCoverageIgnoreStart
        throw new UnsupportedDriverException('PersistentLocker is not supported');
        // @codeCoverageIgnoreEnd
    }
}
