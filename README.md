# Laravel Database Advisory Lock [![Build Status](https://github.com/mpyw/laravel-database-advisory-lock/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/mpyw/laravel-database-advisory-lock/actions) [![Coverage Status](https://coveralls.io/repos/github/mpyw/laravel-database-advisory-lock/badge.svg?branch=master)](https://coveralls.io/github/mpyw/laravel-database-advisory-lock?branch=master)

Advisory Locking Features of Postgres/MySQL on Laravel

## Requirements

| Package | Version                                                | Mandatory |
|:--------|:-------------------------------------------------------|:---------:|
| PHP     | <code>^8.0.2</code>                                    |     ✅     |
| Laravel | <code>^8.0 &#124;&#124; ^9.0 &#124;&#124; ^10.0</code> |     ✅     |
| PHPStan | <code>&gt;=1.1</code>                                  |           |

## Installing

```
composer require mpyw/laravel-database-advisory-lock:^4.1
```

## Basic usage

The default implementation is provided by `ConnectionServiceProvider`, however, **package discovery is not available**.
Be careful that you MUST register it in **`config/app.php`** by yourself.

```php
<?php

return [

    /* ... */

    'providers' => [
        /* ... */

        Mpyw\LaravelDatabaseAdvisoryLock\ConnectionServiceProvider::class,

        /* ... */
    ],

];
```

```php
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\ConnectionInterface;

// Postgres/MySQL
$result = DB::advisoryLocker()
    ->forSession()
    ->withLocking('<key>', function (ConnectionInterface $conn) {
        // critical section here
        return ...;
    });

// Postgres only feature
$result = DB::transaction(function (ConnectionInterface $conn) {
    $conn->advisoryLocker()->forTransaction()->lockOrFail('<key>');
        
    // critical section here
    return ...;
});

// MySQL only feature
$result = DB::advisoryLocker()
    ->forSession()
    ->withLocking('<key>', function (ConnectionInterface $conn) {
        // critical section here
        return ...;
    }, timeout: 5);
```

## Advanced Usage

You can extend Connection classes with `AdvisoryLocks` trait by yourself.

```php
<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('pgsql', function (...$parameters) {
            return new PostgresConnection(...$parameters);
        });
    }
}
```

```php
<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use Mpyw\LaravelDatabaseAdvisoryLock\AdvisoryLocks;

class PostgresConnection extends BasePostgresConnection
{
    use AdvisoryLocks;
}
```

## Implementation Details

### Key Hashing Algorithm

- Postgres advisory locking functions only accepts integer keys. So the driver converts key strings into 64-bit integers through `hashtext()` function.
- MySQL advisory locking functions accepts string keys but their length are limited within 64 bytes. When key strings exceed 64 bytes limit, the driver takes first 24 bytes from them and appends 40 bytes `sha1()` hashes.
- With either hashing algorithm, collisions can theoretically occur with very low probability.

### Transaction-Level Locks

- MySQL does not support native transaction-level advisory locking.
- Postgres supports native transaction-level advisory locking.
  - Locks can be acquired at any transaction scope.
  - You do not need to and cannot manually release locks that have been acquired.

### Session-Level Locks

- MySQL supports session-level advisory locking.
  - An optional wait timeout can be set.
- Postgres supports session-level advisory locking.
  - There was a problem where the automatic lock release algorithm did not work properly, but this has been fixed in version 4.0.0. See [#2](https://github.com/mpyw/laravel-database-advisory-lock/pull/2) for details.
