<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\PHPStan;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

use function is_a;

final class ConnectionClassExtension implements MethodsClassReflectionExtension
{
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $methodName === 'advisoryLocker'
            && (
                is_a($classReflection->getName(), ConnectionInterface::class, true)
                || is_a($classReflection->getName(), DB::class, true)
            );
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new AdvisoryLockerMethod($classReflection);
    }
}
