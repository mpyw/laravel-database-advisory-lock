<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\TransactionTerminationListener;
use Throwable;
use WeakMap;

use function spl_object_hash;

/**
 * class TransactionEventHub
 *
 * Associate an event dispatcher on a connection with a listener of session-level locks.
 * WeakMap prevents memory leaks.
 */
final class TransactionEventHub
{
    /**
     * @var WeakMap<Dispatcher, array<string, TransactionTerminationListener>>
     */
    private WeakMap $dispatchersAndListeners;

    /**
     * @var null|callable(): self
     */
    private static $resolver;

    /**
     * Set a singleton instance resolver.
     *
     * @param null|callable(): self $resolver
     */
    public static function setResolver(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Create or retrieve a singleton instance through resolver.
     */
    public static function resolve(): ?self
    {
        return self::$resolver ? (self::$resolver)() : null;
    }

    public function __construct()
    {
        $this->dispatchersAndListeners = new WeakMap();
    }

    /**
     * Register self::onTransactionTerminated() as a listener once per connection.
     */
    public function initializeWithDispatcher(Dispatcher $dispatcher): void
    {
        if (!isset($this->dispatchersAndListeners[$dispatcher])) {
            $dispatcher->listen(
                [TransactionCommitted::class, TransactionRolledBack::class],
                [self::class, 'onTransactionTerminated'],
            );
        }

        $this->dispatchersAndListeners[$dispatcher] ??= [];
    }

    /**
     * Register underlying user listener per connection.
     * Listeners registered here are invoked only once.
     */
    public function registerOnceListener(TransactionTerminationListener $listener): void
    {
        foreach ($this->dispatchersAndListeners as $dispatcher => $_) {
            $this->dispatchersAndListeners[$dispatcher][spl_object_hash($listener)] = $listener;
        }
    }

    /**
     * Fire on events.
     */
    public function onTransactionTerminated(TransactionCommitted|TransactionRolledBack $event): void
    {
        /** @var array<string, array<string, TransactionTerminationListener>> $savedListenerGroups */
        $savedListenerGroups = [];

        // First, save all listeners.
        foreach ($this->dispatchersAndListeners as $dispatcher => $listeners) {
            foreach ($listeners as $listener) {
                $savedListenerGroups[spl_object_hash($dispatcher)][spl_object_hash($listener)] = $listener;
            }
        }

        // Next, remove listeners in advance.
        foreach ($this->dispatchersAndListeners as $dispatcher => $_) {
            $this->dispatchersAndListeners[$dispatcher] = [];
        }

        // Finally, run the saved listeners.
        // It does not matter if new listeners are registered again during the execution.
        foreach ($savedListenerGroups as $savedListeners) {
            foreach ($savedListeners as $listener) {
                try {
                    $listener->onTransactionTerminated($event);
                    // @codeCoverageIgnoreStart
                } catch (Throwable) {
                }
                // @codeCoverageIgnoreEnd
            }
        }
    }
}
