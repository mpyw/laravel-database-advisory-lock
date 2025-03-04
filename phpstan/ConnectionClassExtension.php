<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\PHPStan;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

final class ConnectionClassExtension implements MethodsClassReflectionExtension
{
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $methodName === 'advisoryLocker'
            && (
                $classReflection->is(ConnectionInterface::class)
                || $classReflection->is(DB::class)
            );
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new AdvisoryLockerMethod($classReflection);
    }
}
