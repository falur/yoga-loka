<?php

declare(strict_types=1);

namespace Tools\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final class RequireNamedArgumentsRule implements Rule
{
    private const string ERROR_MESSAGE = 'Calls with two or more ordinary arguments must use named arguments.';
    private const string ERROR_IDENTIFIER = 'project.namedArgumentsRequired';

    private string|null $currentFile = null;

    /**
     * PHPStan can visit the same nullsafe-call arguments more than once during analysis.
     *
     * @var array<int, true>
     */
    private array $reportedArguments = [];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
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
        $this->resetReportedArguments(file: $scope->getFile());

        $arguments = $this->resolveArguments($node);
        if ($arguments === []) {
            return [];
        }

        if ($this->hasUnpackedArgument(arguments: $arguments) || $this->isVariadicCall(node: $node, scope: $scope)) {
            return [];
        }

        $ordinaryArguments = \array_values(\array_filter(
            array: $arguments,
            callback: static fn(Arg $argument): bool => !$argument->unpack,
        ));

        if (\count($ordinaryArguments) < 2) {
            return [];
        }

        $errors = [];
        foreach ($ordinaryArguments as $argument) {
            if ($argument->name !== null) {
                continue;
            }

            $argumentId = \spl_object_id($argument);
            if (isset($this->reportedArguments[$argumentId])) {
                continue;
            }

            $this->reportedArguments[$argumentId] = true;
            $errors[] = RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier(self::ERROR_IDENTIFIER)
                ->line($argument->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function resetReportedArguments(string $file): void
    {
        if ($this->currentFile === $file) {
            return;
        }

        $this->currentFile = $file;
        $this->reportedArguments = [];
    }

    /**
     * @return list<Arg>
     */
    private function resolveArguments(Node $node): array
    {
        if ($node instanceof CallLike && !$node->isFirstClassCallable()) {
            return \array_values($node->getArgs());
        }

        if ($node instanceof Attribute) {
            return $node->args;
        }

        return [];
    }

    /**
     * @param list<Arg> $arguments
     */
    private function hasUnpackedArgument(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if ($argument->unpack) {
                return true;
            }
        }

        return false;
    }

    private function isVariadicCall(Node $node, Scope $scope): bool
    {
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            if (!$this->reflectionProvider->hasFunction(nameNode: $node->name, namespaceAnswerer: $scope)) {
                return false;
            }

            return $this->hasVariadicVariant(
                variants: $this->reflectionProvider
                    ->getFunction(nameNode: $node->name, namespaceAnswerer: $scope)
                    ->getVariants(),
            );
        }

        if (($node instanceof MethodCall || $node instanceof NullsafeMethodCall) && $node->name instanceof Identifier) {
            return $this->hasVariadicVariant(
                variants: $scope
                    ->getMethodReflection(typeWithMethod: $scope->getType($node->var), methodName: $node->name->toString())
                    ?->getVariants() ?? [],
            );
        }

        if ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Identifier) {
            return $this->hasVariadicVariant(
                variants: $scope
                    ->getMethodReflection(typeWithMethod: $scope->resolveTypeByName($node->class), methodName: $node->name->toString())
                    ?->getVariants() ?? [],
            );
        }

        if (!$node instanceof New_ || !$node->class instanceof Name) {
            return false;
        }

        if (!$this->reflectionProvider->hasClass(className: $scope->resolveName($node->class))) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass(className: $scope->resolveName($node->class));
        if (!$classReflection->hasConstructor()) {
            return false;
        }

        return $this->hasVariadicVariant(variants: $classReflection->getConstructor()->getVariants());
    }

    /**
     * @param array<int, ParametersAcceptor> $variants
     */
    private function hasVariadicVariant(array $variants): bool
    {
        foreach ($variants as $variant) {
            if ($variant->isVariadic()) {
                return true;
            }
        }

        return false;
    }
}
