<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\SessionLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\TransactionLocker;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\UnsupportedDriverException;

class LockerFactory implements Contracts\LockerFactory
{
    protected ?TransactionLocker $transaction = null;
    protected ?SessionLocker $session = null;

    public function __construct(
        protected Connection $connection,
    ) {
    }

    public function forTransaction(): TransactionLocker
    {
        if ($this->connection instanceof PostgresConnection) {
            return $this->transaction ??= new PostgresTransactionLocker($this->connection);
        }

        // @codeCoverageIgnoreStart
        throw new UnsupportedDriverException('TransactionAwareLocker is not supported');
        // @codeCoverageIgnoreEnd
    }

    public function forSession(): SessionLocker
    {
        if ($this->connection instanceof MySqlConnection) {
            return $this->session ??= new MySqlSessionLocker($this->connection);
        }
        if ($this->connection instanceof PostgresConnection) {
            return $this->session ??= new PostgresSessionLocker($this->connection);
        }
        // @codeCoverageIgnoreStart
        throw new UnsupportedDriverException('PersistentLocker is not supported');
        // @codeCoverageIgnoreEnd
    }
}
