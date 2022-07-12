<?php

declare(strict_types=1);

namespace Mpyw\LaravelDatabaseAdvisoryLock\PHPStan;

use Illuminate\Support\Facades\DB;
use Mpyw\LaravelDatabaseAdvisoryLock\Contracts\LockerFactory;
use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function is_a;

final class AdvisoryLockerMethod implements MethodReflection
{
    private ClassReflection $class;

    public function __construct(ClassReflection $classReflection)
    {
        $this->class = $classReflection;
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->class;
    }

    public function isStatic(): bool
    {
        return is_a($this->class->getName(), DB::class, true);
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function getName(): string
    {
        return 'advisoryLocker';
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    public function getVariants(): array
    {
        return [new FunctionVariant(
            TemplateTypeMap::createEmpty(),
            null,
            [],
            false,
            new ObjectType(LockerFactory::class),
        )];
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): ?Type
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }
}
