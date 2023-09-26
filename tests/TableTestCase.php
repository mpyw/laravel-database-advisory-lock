<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class TableTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection('pgsql');

        $schema->dropIfExists('users');
        $schema->create('users', static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->unique();
        });
    }
}
