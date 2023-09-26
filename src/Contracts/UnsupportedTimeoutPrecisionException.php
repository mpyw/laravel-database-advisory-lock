<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Contracts;

use BadMethodCallException;

/**
 * class UnsupportedTimeoutPrecisionException
 *
 * You can't use float timeout values for this connection.
 */
class UnsupportedTimeoutPrecisionException extends BadMethodCallException {}
