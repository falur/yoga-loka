<?php

declare(strict_types=1);

namespace Tools\PHPStan\TypeContracts;

final class TypeContractViolation
{
    public const NO_IMPLICIT_MIXED_TYPE = 'project.noImplicitMixedType';
    public const NO_NESTED_ARRAY_TYPE = 'project.noNestedArrayType';
    public const NO_ARRAY_SHAPE_TYPE = 'project.noArrayShapeType';

    public function __construct(
        private readonly string $identifier,
        private readonly string $message,
    ) {}

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function message(): string
    {
        return $this->message;
    }
}
