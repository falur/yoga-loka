<?php

declare(strict_types=1);

namespace Tools\OpenApi\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\ClassMethod>
 */
final class OpenApiAttributeRule implements Rule
{
    /** @var array<string, string> */
    private array $ids = [];

    public function getNodeType(): string
    {
        return Node\Stmt\ClassMethod::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->attributes($node) as $attribute) {
            if (!$this->attributeIsOpenApi($attribute)) {
                continue;
            }

            if ($this->ignore($attribute)) {
                continue;
            }

            $id = $this->id($attribute);

            if ($id === '') {
                $errors[] = RuleErrorBuilder::message('OpenApi id не должен быть пустым.')
                    ->identifier('openApi.emptyId')
                    ->build();
                continue;
            }

            if (!\preg_match('/^[a-z][a-zA-Z0-9_]*$/', $id)) {
                $errors[] = RuleErrorBuilder::message(\sprintf('OpenApi id имеет недопустимый формат: %s.', $id))
                    ->identifier('openApi.invalidId')
                    ->build();
            }

            if (isset($this->ids[$id])) {
                $errors[] = RuleErrorBuilder::message(\sprintf('OpenApi id уже используется: %s.', $id))
                    ->identifier('openApi.duplicateId')
                    ->build();
            }

            $this->ids[$id] = $scope->getFile();
        }

        return $errors;
    }

    /**
     * @return list<Attribute>
     */
    private function attributes(Node\Stmt\ClassMethod $method): array
    {
        $attributes = [];

        foreach ($method->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    private function attributeIsOpenApi(Attribute $attribute): bool
    {
        $attributeName = $attribute->name->toString();

        return $attributeName === 'OpenApi' || \str_ends_with($attributeName, '\\OpenApi');
    }

    private function id(Attribute $attribute): string
    {
        foreach ($attribute->args as $index => $argument) {
            if ($argument->name?->toString() !== 'id' && !($argument->name === null && $index === 0)) {
                continue;
            }

            if ($argument->value instanceof Scalar\String_) {
                return $argument->value->value;
            }

            if ($argument->value instanceof Expr\ConstFetch) {
                return '';
            }
        }

        return '';
    }

    private function ignore(Attribute $attribute): bool
    {
        foreach ($attribute->args as $index => $argument) {
            if ($argument->name?->toString() !== 'ignore' && !($argument->name === null && $index === 2)) {
                continue;
            }

            if ($argument->value instanceof Expr\ConstFetch) {
                return \strtolower($argument->value->name->toString()) === 'true';
            }
        }

        return false;
    }
}
