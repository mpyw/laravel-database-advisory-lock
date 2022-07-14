<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Support\ServiceProvider;

/**
 * class AdvisoryLockServiceProvider
 *
 * Automatically registered through package discovery.
 */
final class AdvisoryLockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TransactionEventHub::class);
    }

    public function boot(TransactionEventHub $hub): void
    {
        TransactionEventHub::setResolver(fn () => $hub);
    }
}
