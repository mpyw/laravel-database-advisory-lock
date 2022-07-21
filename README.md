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
composer require mpyw/laravel-database-advisory-lock:^4.2.1
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

// Postgres/MySQL: Session-Level Locking
$result = DB::advisoryLocker()
    ->forSession()
    ->withLocking('<key>', function (ConnectionInterface $conn) {
        // critical section here
        return ...;
    }); // no wait
$result = DB::advisoryLocker()
    ->forSession()
    ->withLocking('<key>', function (ConnectionInterface $conn) {
        // critical section here
        return ...;
    }, timeout: 5); // wait for 5 seconds or fail
$result = DB::advisoryLocker()
    ->forSession()
    ->withLocking('<key>', function (ConnectionInterface $conn) {
        // critical section here
        return ...;
    }, timeout: -1); // infinite wait

// Postgres only feature: Transaction-Level Locking (no wait)
$result = DB::transaction(function (ConnectionInterface $conn) {
    $conn->advisoryLocker()->forTransaction()->lockOrFail('<key>');
    // critical section here
    return ...;
});
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

```sql
-- Postgres: int8
hashtext('<key>')
```

```sql
-- MySQL: varchar(64)
CASE WHEN CHAR_LENGTH('<key>') > 64
THEN CONCAT(SUBSTR('<key>', 1, 24), SHA1('<key>'))
ELSE '<key>'
END
```

- Postgres advisory locking functions only accepts integer keys. So the driver converts key strings into 64-bit integers through `hashtext()` function.
- MySQL advisory locking functions accepts string keys but their length are limited within 64 chars. When key strings exceed 64 chars limit, the driver takes first 24 chars from them and appends 40 chars `sha1()` hashes.
- With either hashing algorithm, collisions can theoretically occur with very low probability.

### Locking Methods

|                           | Postgres  | MySQL  |
|:--------------------------|:---------:|:------:|
| Session-Level Locking     |     ✅     |   ✅    |
| Transaction-Level Locking |     ✅     |   ❌    |

- Session-Level locks can be acquired anywhere.
  - They can be released manually or automatically through a destructor.
  - For Postgres, there was a problem where the automatic lock release algorithm did not work properly, but this has been fixed in version 4.0.0. See [#2](https://github.com/mpyw/laravel-database-advisory-lock/pull/2) for details.
- Transaction-Level locks can be acquired within a transaction.
  - You do not need to and cannot manually release locks that have been acquired.

### Timeout Values

|                                            |     Postgres     | MySQL  |
|:-------------------------------------------|:----------------:|:------:|
| Timeout: `0` (default; immediate, no wait) |        ✅         |   ✅    |
| Timeout: `positive-int`                    | ✅<br>(Emulated)  |   ✅    |
| Timeout: `negative-int` (infinite wait)    |        ✅         |   ✅    |

- Postgres does not natively support waiting for a finite specific amount of time, but this is emulated by looping through a temporary function.
