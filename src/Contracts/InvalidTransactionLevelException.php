<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use BadMethodCallException;

/**
 * class InvalidTransactionLevelException
 *
 * You can't use TransactionLocker outside of transaction.
 */
class InvalidTransactionLevelException extends BadMethodCallException
{
}
