<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Mpyw\LaravelDatabaseAdvisoryLock\TransactionEventHub;
use PHPUnit\Framework\TestCase as BaseTestCase;

final class TransactionEventHubTest extends BaseTestCase
{
    public function testInitializeWithNullDispatcherIsNoop(): void
    {
        $hub = new TransactionEventHub();

        // A connection is not guaranteed to expose an event dispatcher
        // (Connection::getEventDispatcher() is nullable on newer Laravel),
        // so passing null must be a harmless no-op rather than a TypeError.
        $hub->initializeWithDispatcher(null);

        $this->expectNotToPerformAssertions();
    }
}
