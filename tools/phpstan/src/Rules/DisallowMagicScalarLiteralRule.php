<?php

declare(strict_types=1);

namespace Tools\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final class DisallowMagicScalarLiteralRule implements Rule
{
    private const string ERROR_MESSAGE = 'Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.';
    private const string ERROR_IDENTIFIER = 'project.magicScalarLiteral';

    /**
     * @param list<string> $analysedPathFragments
     */
    public function __construct(
        private readonly array $analysedPathFragments = ['/app/src/'],
    ) {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->shouldAnalyseFile(file: $scope->getFile())) {
            return [];
        }

        if (!$this->isRootNode(node: $node)) {
            return [];
        }

        return $this->inspectNode(node: $node, ancestors: [], scope: $scope);
    }

    private function isRootNode(Node $node): bool
    {
        return $node instanceof Stmt\ClassMethod
            || $node instanceof Stmt\Function_
            || $node instanceof Stmt\Property;
    }

    /**
     * @param list<Node> $ancestors
     * @return list<RuleError>
     */
    private function inspectNode(Node $node, array $ancestors, Scope $scope): array
    {
        if ($node instanceof Attribute || $node instanceof Stmt\ClassConst || $node instanceof Stmt\EnumCase) {
            return [];
        }

        $errors = [];

        if ($this->isForbiddenLiteralCandidate(node: $node) && !$this->isAllowedContext(node: $node, ancestors: $ancestors, scope: $scope)) {
            $errors[] = RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier(self::ERROR_IDENTIFIER)
                ->line($node->getStartLine())
                ->build();
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};

            if ($subNode instanceof Node) {
                foreach ($this->inspectNode(node: $subNode, ancestors: [...$ancestors, $node], scope: $scope) as $error) {
                    $errors[] = $error;
                }

                continue;
            }

            if (!\is_array(value: $subNode)) {
                continue;
            }

            foreach ($subNode as $subNodeItem) {
                if (!$subNodeItem instanceof Node) {
                    continue;
                }

                foreach ($this->inspectNode(node: $subNodeItem, ancestors: [...$ancestors, $node], scope: $scope) as $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    private function shouldAnalyseFile(string $file): bool
    {
        $normalizedFile = \str_replace(search: '\\', replace: '/', subject: $file);

        foreach ($this->analysedPathFragments as $analysedPathFragment) {
            if (\str_contains(haystack: $normalizedFile, needle: $analysedPathFragment)) {
                return true;
            }
        }

        return false;
    }

    private function isForbiddenLiteralCandidate(Node $node): bool
    {
        if ($node instanceof Scalar\String_) {
            return $node->value !== '';
        }

        if ($node instanceof Scalar\LNumber) {
            return !\in_array(needle: $node->value, haystack: [0, 1], strict: true);
        }

        if ($node instanceof Scalar\DNumber) {
            return true;
        }

        if (!$node instanceof Expr\ConstFetch) {
            return false;
        }

        $constantName = \strtolower(string: $node->name->toString());

        return $constantName === 'true' || $constantName === 'false';
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedContext(Node $node, array $ancestors, Scope $scope): bool
    {
        foreach ($ancestors as $ancestor) {
            if ($ancestor instanceof Attribute || $ancestor instanceof Stmt\ClassConst || $ancestor instanceof Stmt\EnumCase) {
                return true;
            }
        }

        if ($this->isAllowedExceptionClass(scope: $scope)) {
            return true;
        }

        if ($this->isAllowedParameterDefault(node: $node, ancestors: $ancestors)) {
            return true;
        }

        if ($this->isAllowedNamedBooleanArgument(node: $node, ancestors: $ancestors)) {
            return true;
        }

        if ($this->isAllowedCallableArrayMethodName(node: $node, ancestors: $ancestors)) {
            return true;
        }

        if ($this->isAllowedStringHelperLiteral(node: $node, ancestors: $ancestors)) {
            return true;
        }

        if ($this->isAllowedFunctionArgument(ancestors: $ancestors)) {
            return true;
        }

        if ($this->isAllowedExceptionMessage(ancestors: $ancestors)) {
            return true;
        }

        if ($this->isAllowedConsoleOrLoggerMessage(ancestors: $ancestors)) {
            return true;
        }

        if ($this->isAllowedResponseMessage(ancestors: $ancestors)) {
            return true;
        }

        if ($this->isAllowedViewTemplatePath(ancestors: $ancestors)) {
            return true;
        }

        return false;
    }

    private function isAllowedExceptionClass(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return false;
        }

        return \str_ends_with(haystack: $classReflection->getName(), needle: 'Exception');
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedParameterDefault(Node $node, array $ancestors): bool
    {
        for ($index = \count(value: $ancestors) - 1; $index >= 0; $index--) {
            $ancestor = $ancestors[$index];

            if ($ancestor instanceof Node\Param) {
                return $ancestor->default === $node;
            }
        }

        return false;
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedNamedBooleanArgument(Node $node, array $ancestors): bool
    {
        if (!$this->isBooleanLiteral(node: $node)) {
            return false;
        }

        $argument = $this->nearestArgument(ancestors: $ancestors);

        if (!$argument instanceof Arg || $argument->name === null) {
            return false;
        }

        $call = $this->callForNearestArgument(ancestors: $ancestors);

        return $call instanceof Expr\FuncCall
            || $call instanceof Expr\MethodCall
            || $call instanceof Expr\StaticCall;
    }

    private function isBooleanLiteral(Node $node): bool
    {
        if (!$node instanceof Expr\ConstFetch) {
            return false;
        }

        $constantName = \strtolower(string: $node->name->toString());

        return $constantName === 'true' || $constantName === 'false';
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedCallableArrayMethodName(Node $node, array $ancestors): bool
    {
        if (!$node instanceof Scalar\String_) {
            return false;
        }

        $arrayItem = $this->nearestArrayItem(ancestors: $ancestors);

        if (!$arrayItem instanceof Node\ArrayItem || $arrayItem->value !== $node) {
            return false;
        }

        $array = $this->arrayForNearestArrayItem(ancestors: $ancestors);

        if (!$array instanceof Expr\Array_ || \count(value: $array->items) !== 2) {
            return false;
        }

        [$classItem, $methodItem] = $array->items;

        if ($methodItem !== $arrayItem || $classItem->key !== null || $methodItem->key !== null) {
            return false;
        }

        if (!$classItem->value instanceof Expr\ClassConstFetch || !$classItem->value->name instanceof Node\Identifier) {
            return false;
        }

        return \strtolower(string: $classItem->value->name->toString()) === 'class';
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedStringHelperLiteral(Node $node, array $ancestors): bool
    {
        if (!$node instanceof Scalar\String_) {
            return false;
        }

        $call = $this->callForNearestArgument(ancestors: $ancestors);

        if ($call instanceof Expr\FuncCall) {
            return $this->isFunctionCallNamed(call: $call, name: 'sprintf')
                || $this->isFunctionCallNamed(call: $call, name: 'str');
        }

        return $call instanceof Expr\MethodCall && $this->isMethodCallOnStrHelperChain(call: $call);
    }

    private function isMethodCallOnStrHelperChain(Expr\MethodCall $call): bool
    {
        $target = $call->var;

        while ($target instanceof Expr\MethodCall) {
            $target = $target->var;
        }

        return $target instanceof Expr\FuncCall && $this->isFunctionCallNamed(call: $target, name: 'str');
    }

    private function isFunctionCallNamed(Expr\FuncCall $call, string $name): bool
    {
        return $call->name instanceof Name
            && \strtolower(string: $call->name->toString()) === $name;
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedFunctionArgument(array $ancestors): bool
    {
        $call = $this->callForNearestArgument(ancestors: $ancestors);

        if (!$call instanceof Expr\FuncCall) {
            return false;
        }

        if (!$call->name instanceof Name) {
            return false;
        }

        return \in_array(
            needle: \strtolower(string: $call->name->toString()),
            haystack: [
                'array_key_exists',
                'count',
                'dirname',
                'explode',
                'file_get_contents',
                'implode',
                'in_array',
                'is_file',
                'is_dir',
                'json_encode',
                'ltrim',
                'mkdir',
                'preg_match',
                'rtrim',
                'sprintf',
                'str_contains',
                'str_ends_with',
                'str_replace',
                'str_starts_with',
                'strtolower',
                'strtr',
                'substr',
                'trim',
            ],
            strict: true,
        );
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedExceptionMessage(array $ancestors): bool
    {
        $new = $this->callForNearestArgument(ancestors: $ancestors);

        if (!$new instanceof Expr\New_) {
            return false;
        }

        if (!$new->class instanceof Name) {
            return false;
        }

        return \str_ends_with(haystack: $new->class->toString(), needle: 'Exception');
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedConsoleOrLoggerMessage(array $ancestors): bool
    {
        $call = $this->callForNearestArgument(ancestors: $ancestors);

        if (!$call instanceof Expr\MethodCall && !$call instanceof Expr\StaticCall) {
            return false;
        }

        if (!$call->name instanceof Node\Identifier) {
            return false;
        }

        return \in_array(
            needle: $call->name->toString(),
            haystack: ['alert', 'comment', 'debug', 'error', 'info', 'line', 'log', 'warning', 'write', 'writeln'],
            strict: true,
        );
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedResponseMessage(array $ancestors): bool
    {
        $argument = $this->nearestArgument(ancestors: $ancestors);
        $new = $this->callForNearestArgument(ancestors: $ancestors);

        if (!$argument instanceof Arg || $argument->name?->toString() !== 'message') {
            return false;
        }

        if (!$new instanceof Expr\New_ || !$new->class instanceof Name) {
            return false;
        }

        return \str_ends_with(haystack: $new->class->toString(), needle: 'Response');
    }

    /**
     * @param list<Node> $ancestors
     */
    private function isAllowedViewTemplatePath(array $ancestors): bool
    {
        $argument = $this->nearestArgument(ancestors: $ancestors);
        $call = $this->callForNearestArgument(ancestors: $ancestors);

        if (!$argument instanceof Arg || $argument->name?->toString() !== 'path') {
            return false;
        }

        if (!$call instanceof Expr\MethodCall || !$call->name instanceof Node\Identifier) {
            return false;
        }

        return $call->name->toString() === 'render';
    }

    /**
     * @param list<Node> $ancestors
     */
    private function nearestArgument(array $ancestors): ?Arg
    {
        for ($index = \count(value: $ancestors) - 1; $index >= 0; $index--) {
            $ancestor = $ancestors[$index];

            if ($ancestor instanceof Arg) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * @param list<Node> $ancestors
     */
    private function nearestArrayItem(array $ancestors): ?Node\ArrayItem
    {
        for ($index = \count(value: $ancestors) - 1; $index >= 0; $index--) {
            $ancestor = $ancestors[$index];

            if ($ancestor instanceof Node\ArrayItem) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * @param list<Node> $ancestors
     */
    private function arrayForNearestArrayItem(array $ancestors): ?Expr\Array_
    {
        for ($index = \count(value: $ancestors) - 1; $index >= 0; $index--) {
            $ancestor = $ancestors[$index];

            if (!$ancestor instanceof Node\ArrayItem) {
                continue;
            }

            $array = $ancestors[$index - 1] ?? null;

            return $array instanceof Expr\Array_ ? $array : null;
        }

        return null;
    }

    /**
     * @param list<Node> $ancestors
     */
    private function callForNearestArgument(array $ancestors): ?Node
    {
        for ($index = \count(value: $ancestors) - 1; $index >= 0; $index--) {
            $ancestor = $ancestors[$index];

            if (!$ancestor instanceof Arg) {
                continue;
            }

            return $ancestors[$index - 1] ?? null;
        }

        return null;
    }
}
