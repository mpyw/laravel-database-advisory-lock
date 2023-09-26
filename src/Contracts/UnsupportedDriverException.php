<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use DomainException;

/**
 * class UnsupportedDriverException
 *
 * Requested operation is not supported on the driver.
 */
class UnsupportedDriverException extends DomainException {}
