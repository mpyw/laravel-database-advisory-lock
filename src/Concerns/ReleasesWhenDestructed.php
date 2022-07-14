<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Concerns;

trait ReleasesWhenDestructed
{
    abstract public function release(): bool;

    public function __destruct()
    {
        $this->release();
    }
}
