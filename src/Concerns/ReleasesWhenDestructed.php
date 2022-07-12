<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Concerns;

/**
 * @internal
 */
trait ReleasesWhenDestructed
{
    abstract public function release(): bool;

    public function __destruct()
    {
        $this->release();
    }
}
