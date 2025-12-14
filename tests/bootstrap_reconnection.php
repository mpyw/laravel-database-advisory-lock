<?php

declare(strict_types=1);

/*
 * Bootstrap for ReconnectionToleranceTest.
 *
 * Defines a stub DetectsLostConnections trait BEFORE the autoloader is registered,
 * so that the real trait from Laravel is never loaded.
 */

namespace Illuminate\Database;

use Throwable;

trait DetectsLostConnections
{
    protected function causedByLostConnection(Throwable $e): bool
    {
        return true;
    }
}

namespace;

require __DIR__ . '/../vendor/autoload.php';
