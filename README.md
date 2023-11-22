# Laravel Database Advisory Lock [![Build Status](https://github.com/mpyw/laravel-database-advisory-lock/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/mpyw/laravel-database-advisory-lock/actions) [![Coverage Status](https://coveralls.io/repos/github/mpyw/laravel-database-advisory-lock/badge.svg?branch=master)](https://coveralls.io/github/mpyw/laravel-database-advisory-lock?branch=master)

Advisory Locking Features of Postgres/MySQL/MariaDB on Laravel

## Requirements

| Package | Version                              | Mandatory |
|:--------|:-------------------------------------|:---------:|
| PHP     | <code>^8.0.2</code>                  |     ✅     |
| Laravel | <code>^9.0 &#124;&#124; ^10.0</code> |     ✅     |
| PHPStan | <code>&gt;=1.1</code>                |           |

| RDBMS    | Version                   |
|:---------|:--------------------------|
| Postgres | <code>&gt;=9.1.14</code>  |
| MySQL    | <code>&gt;=5.7.5</code>   |
| MariaDB  | <code>&gt;=10.0.15</code> |

## Installing

```
composer require mpyw/laravel-database-advisory-lock:^4.3
```

## Basic usage

> [!IMPORTANT]
> The default implementation is provided by `ConnectionServiceProvider`, however, **package discovery is not available**.
> Be careful that you MUST register it in **`config/app.php`** by yourself.

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

// Session-Level Locking
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
    }, timeout: -1); // infinite wait (except MariaDB)

// Postgres only feature: Transaction-Level Locking (no wait)
$result = DB::transaction(function (ConnectionInterface $conn) {
    $conn->advisoryLocker()->forTransaction()->lockOrFail('<key>');
    // critical section here
    return ...;
});
```

## Advanced Usage

> [!TIP]
> You can extend Connection classes with `AdvisoryLocks` trait by yourself.

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
-- MySQL/MariaDB: varchar(64)
CASE WHEN CHAR_LENGTH('<key>') > 64
THEN CONCAT(SUBSTR('<key>', 1, 24), SHA1('<key>'))
ELSE '<key>'
END
```

- Postgres advisory locking functions only accept integer keys. So the driver converts key strings into 64-bit integers through `hashtext()` function.
  - An empty string can also be used as a key.
- MySQL advisory locking function accepts string keys but their length are limited within 64 chars. When key strings exceed 64 chars limit, the driver takes first 24 chars from them and appends 40 chars `sha1()` hashes.
  - MariaDB's limit is actually 192 bytes, unlike MySQL's 64 chars. However, the key hashing algorithm is equivalent.
  - MariaDB accepts an empty string as a key, but does not actually lock anything. MySQL, on the other hand, raises an error for empty string keys.
- With either hashing algorithm, collisions can theoretically occur with very low probability.

### Locking Methods

|                           | Postgres  | MySQL/MariaDB |
|:--------------------------|:---------:|:-------------:|
| Session-Level Locking     |     ✅     |       ✅       |
| Transaction-Level Locking |     ✅     |       ❌       |

- Session-Level locks can be acquired anywhere.
  - They can be released manually or automatically through a destructor.
  - For Postgres, there was a problem where the automatic lock release algorithm did not work properly, but this has been fixed in version 4.0.0. See [#2](https://github.com/mpyw/laravel-database-advisory-lock/pull/2) for details.
- Transaction-Level locks can be acquired within a transaction.
  - You do not need to and cannot manually release locks that have been acquired.

### Timeout Values

|                                            |    Postgres     | MySQL | MariaDB |
|:-------------------------------------------|:---------------:|:-----:|:-------:|
| Timeout: `0` (default; immediate, no wait) |        ✅        |   ✅   |    ✅    |
| Timeout: `positive-int`                    |        ✅        |   ✅   |    ✅    |
| Timeout: `negative-int` (infinite wait)    |        ✅        |   ✅   |    ❌    |
| Timeout: `float`                           |        ✅        |   ❌   |    ❌    |

- Postgres does not natively support waiting for a finite specific amount of time, but this is emulated by looping through a temporary function.
- MariaDB does not accept infinite timeouts. very large numbers can be used instead.
- Float precision is not supported on MySQL/MariaDB.

## Caveats about Transaction Levels

### Key Principle

Always avoid nested transactions when using advisory locks to ensure adherence to the **[S2PL (Strict 2-Phase Locking)](https://en.wikipedia.org/wiki/Two-phase_locking#Strict_two-phase_locking)** principle.

### Recommended Approach

When transactions and advisory locks are related, either locking approach can be applied.

> [!NOTE]
> **Transaction-Level Locks:**  
> <ins>Acquire the lock at the transaction nesting level 1</ins>, then rely on automatic release mechanisms.
>
> ```php
> if (DB::transactionLevel() > 1) {
>     throw new LogicException("Don't use nested transactions outside of this logic.");
> }
>
> DB::advisoryLocker()
>     ->forTransaction()
>     ->lockOrFail('<key>');
> // critical section with transaction here
> ```

> [!NOTE]
> **Session-Level Locks:**  
> <ins>Acquire the lock at the transaction nesting level 0</ins>, then proceed to call `DB::transaction()` call.
>
> ```php
> if (DB::transactionLevel() > 0) {
>     throw new LogicException("Don't use transactions outside of this logic.");
> }
>
> $result = DB::advisoryLocker()
>     ->forSession()
>     ->withLocking('<key>', fn (ConnectionInterface $conn) => $conn->transaction(function () {
>         // critical section with transaction here
>     }));
> ```

> [!WARNING]
> When writing logic like this, [`DatabaseTruncation`](https://github.com/laravel/framework/blob/87b9e7997e178dfc4acd5e22fa8d77ba333c3abd/src/Illuminate/Foundation/Testing/DatabaseTruncation.php) must be used instead of [`RefreshDatabase`](https://github.com/laravel/framework/blob/87b9e7997e178dfc4acd5e22fa8d77ba333c3abd/src/Illuminate/Foundation/Testing/RefreshDatabase.php).

### Considerations

> [!CAUTION]
> **Transaction-Level Locks:**  
> Don't take transaction-level locks in nested transactions. They are unaware of Laravel's nested transaction emulation.

> [!CAUTION]
> **Session-Level Locks:**  
> Don't take session-level locks in the transactions when the content to be committed by the transaction is related to the advisory locks.

> [!TIP]
> What would happen if we released a session-level lock within a transaction? Let's verify this with a timeline chart, assuming a `READ COMMITTED` isolation level on Postgres. The bank account X is operated from two sessions A and B concurrently.
>
> | Session A                                                          | Session B                                                                                             |
> |:-------------------------------------------------------------------|:------------------------------------------------------------------------------------------------------|
> | `BEGIN`                                                            |                                                                                                       |
> | ︙                                                                  | `BEGIN`                                                                                               |
> | `pg_advisory_lock(X)`                                              | ︙                                                                                                     |
> | ︙                                                                  | `pg_advisory_lock(X)`                                                                                 |
> | Fetch balance of User X<br>(Balance: 1000 USD)                     | ︙                                                                                                     |
> | ︙                                                                  | ︙                                                                                                     |
> | Deduct 800 USD if balance permits<br>(Balance: 1000 USD → 200 USD) | ︙                                                                                                     |
> | ︙                                                                  | ︙                                                                                                     |
> | `pg_advisory_unlock(X)`                                            | ︙                                                                                                     |
> | ︙                                                                  | Fetch balance of User X<br>**(Balance: 1000 USD :heavy_exclamation_mark:)**                           |
> | ︙                                                                  | ︙                                                                                                     |
> | ︙                                                                  | Deduct 800 USD if balance permits<br>**(Balance: 1000 USD → 200 USD :bangbang:)**                     |
> | `COMMIT`                                                           | ︙                                                                                                     |
> | ︙                                                                  | `pg_advisory_unlock(X)`                                                                               |
> | Fetch balance of User X<br>(Balance: 200 USD)                      | ︙                                                                                                     |
> |                                                                    | `COMMIT`                                                                                              |
> |                                                                    | ︙                                                                                                     |
> |                                                                    | Fetch balance of User X<br>(**Balance: <ins>-600 USD</ins>** :interrobang::interrobang::interrobang:) |
