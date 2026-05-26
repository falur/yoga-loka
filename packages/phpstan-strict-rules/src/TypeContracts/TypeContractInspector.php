<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\TypeContracts;

use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

final class TypeContractInspector
{
    /**
     * @return list<TypeContractViolation>
     */
    public function inspect(Type $type): array
    {
        $hasImplicitMixed = false;
        $hasNestedArray = false;
        $hasArrayShape = false;

        TypeTraverser::map(type: $type, cb: function (Type $current, callable $traverse) use (&$hasImplicitMixed, &$hasNestedArray, &$hasArrayShape): Type {
            if ($current instanceof MixedType && !$current instanceof TemplateType && !$current->isExplicitMixed()) {
                $hasImplicitMixed = true;
            }

            if ($current instanceof ConstantArrayType) {
                $hasArrayShape = true;
            }

            if ($current instanceof ArrayType && $this->containsArrayType($current->getItemType())) {
                $hasNestedArray = true;
            }

            return $traverse($current);
        });

        $violations = [];

        if ($hasImplicitMixed) {
            $violations[] = new TypeContractViolation(
                identifier: TypeContractViolation::NO_IMPLICIT_MIXED_TYPE,
                message: 'Type contracts must specify generic types instead of relying on implicit mixed.',
            );
        }

        if ($hasNestedArray) {
            $violations[] = new TypeContractViolation(
                identifier: TypeContractViolation::NO_NESTED_ARRAY_TYPE,
                message: 'Type contracts must not contain nested arrays.',
            );
        }

        if ($hasArrayShape) {
            $violations[] = new TypeContractViolation(
                identifier: TypeContractViolation::NO_ARRAY_SHAPE_TYPE,
                message: 'Type contracts must not contain array shapes or tuple types.',
            );
        }

        return $violations;
    }

    private function containsArrayType(Type $type): bool
    {
        $containsArray = false;

        TypeTraverser::map(type: $type, cb: static function (Type $current, callable $traverse) use (&$containsArray): Type {
            if ($current instanceof ArrayType || $current instanceof ConstantArrayType) {
                $containsArray = true;
            }

            return $traverse($current);
        });

        return $containsArray;
    }
}
