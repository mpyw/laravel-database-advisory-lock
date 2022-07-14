<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use ReflectionException;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ReconnectionToleranceTest extends TestCase
{
    /**
     * @var array<string>
     */
    private array $queries;
    private Dispatcher $events;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        // Make connections to consider all errors as disconnect errors
        eval(
            <<<'EOD'
            namespace Illuminate\Database;
            use Throwable;
            trait DetectsLostConnections
            {
                protected function causedByLostConnection(Throwable $e): bool
                {
                    return true;
                }
            }
            EOD
        );

        parent::setUp();

        $events = $this->app->make(Dispatcher::class);
        assert($events instanceof Dispatcher);
        $this->events = $events;
    }

    protected function startListening(): void
    {
        // Log all prepared queries
        $this->events->listen(
            StatementPrepared::class,
            function (StatementPrepared $event): void {
                $this->queries[] = $event->statement->queryString;
            },
        );
    }

    protected function endListening(): void
    {
        $this->events->forget(StatementPrepared::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->queries = [];
    }

    public function testReconnectionWithoutActiveLocks(): void
    {
        $this->startListening();

        try {
            // MySQL doesn't accept empty locks, so this will trigger QueryException
            DB::connection('mysql')
                ->advisoryLocker()
                ->forSession()
                ->withLocking('', fn () => null);
        } catch (QueryException) {
        }
        $this->endListening();

        // Retries
        $this->assertSame([
            'SELECT GET_LOCK(CASE WHEN LENGTH(?) > 64 THEN CONCAT(SUBSTR(?, 1, 24), SHA1(?)) ELSE ? END, 0)',
            'SELECT GET_LOCK(CASE WHEN LENGTH(?) > 64 THEN CONCAT(SUBSTR(?, 1, 24), SHA1(?)) ELSE ? END, 0)',
        ], $this->queries);
    }

    public function testReconnectionWithActiveLocks(): void
    {
        DB::connection('mysql')
            ->advisoryLocker()
            ->forSession()
            ->withLocking('foo', function (ConnectionInterface $conn): void {
                $this->startListening();

                try {
                    // MySQL doesn't accept empty locks, so this will trigger QueryException
                    $conn
                        ->advisoryLocker()
                        ->forSession()
                        ->withLocking('', fn () => null);
                } catch (QueryException) {
                }
                $this->endListening();
            });

        // No retries
        $this->assertSame([
            'SELECT GET_LOCK(CASE WHEN LENGTH(?) > 64 THEN CONCAT(SUBSTR(?, 1, 24), SHA1(?)) ELSE ? END, 0)',
        ], $this->queries);
    }
}
