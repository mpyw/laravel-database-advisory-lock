<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use BadMethodCallException;

class InvalidTransactionLevelException extends BadMethodCallException
{
}
