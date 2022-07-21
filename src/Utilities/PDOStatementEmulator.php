<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\Utilities;

use PDO;

/**
 * class Emulator
 *
 * @internal
 */
final class PDOStatementEmulator
{
    /**
     * @phpstan-template T
     * @phpstan-param callable(): T $callback
     * @phpstan-return T
     */
    public static function emulated(PDO $pdo, callable $callback): mixed
    {
        $original = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        try {
            return $callback();
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $original);
        }
    }
}
