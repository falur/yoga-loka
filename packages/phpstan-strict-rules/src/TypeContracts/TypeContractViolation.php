<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\TypeContracts;

final class TypeContractViolation
{
    public const string NO_IMPLICIT_MIXED_TYPE = 'gianTiaga.phpstanStrictRules.noImplicitMixedType';
    public const string NO_NESTED_ARRAY_TYPE = 'gianTiaga.phpstanStrictRules.noNestedArrayType';
    public const string NO_ARRAY_SHAPE_TYPE = 'gianTiaga.phpstanStrictRules.noArrayShapeType';

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
