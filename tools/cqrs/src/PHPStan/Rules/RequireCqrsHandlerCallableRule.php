<?php

declare(strict_types=1);

namespace Tools\Cqrs\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Tools\Cqrs\Attribute\Transactional;
use Tools\Cqrs\CommandBusInterface;
use Tools\Cqrs\QueryBusInterface;

/**
 * @implements Rule<MethodCall>
 */
final class RequireCqrsHandlerCallableRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->toString() !== 'dispatch') {
            return [];
        }

        $busType = $this->busType(node: $node, scope: $scope);
        if ($busType === null) {
            return [];
        }

        $handlerArgument = $this->handlerArgument(arguments: \array_values($node->getArgs()));
        if (!$handlerArgument instanceof Arg) {
            return [];
        }

        if (!$this->isHandleFirstClassCallable(argument: $handlerArgument)) {
            return [
                RuleErrorBuilder::message('CQRS dispatch() handler must be passed as first-class callable on handle(...).')
                    ->identifier('cqrs.handlerCallableRequired')
                    ->line($handlerArgument->getStartLine())
                    ->build(),
            ];
        }

        if ($busType !== QueryBusInterface::class) {
            return [];
        }

        if (!$handlerArgument->value instanceof MethodCall || !$handlerArgument->value->name instanceof Identifier) {
            return [];
        }

        if (!$this->hasTransactionalAttribute(handlerCall: $handlerArgument->value, scope: $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Query handlers must not be marked with #[Transactional].')
                ->identifier('cqrs.transactionalQueryHandler')
                ->line($handlerArgument->getStartLine())
                ->build(),
        ];
    }

    /**
     * @return class-string<CommandBusInterface>|class-string<QueryBusInterface>|null
     */
    private function busType(MethodCall $node, Scope $scope): ?string
    {
        $callerType = $scope->getType($node->var);

        if ((new ObjectType(CommandBusInterface::class))->isSuperTypeOf($callerType)->yes()) {
            return CommandBusInterface::class;
        }

        if ((new ObjectType(QueryBusInterface::class))->isSuperTypeOf($callerType)->yes()) {
            return QueryBusInterface::class;
        }

        return null;
    }

    /**
     * @param list<Arg> $arguments
     */
    private function handlerArgument(array $arguments): ?Arg
    {
        foreach ($arguments as $argument) {
            if ($argument->name instanceof Identifier && $argument->name->toString() === 'handler') {
                return $argument;
            }
        }

        return $arguments[1] ?? null;
    }

    private function isHandleFirstClassCallable(Arg $argument): bool
    {
        if (!$argument->value instanceof MethodCall) {
            return false;
        }

        if (!$argument->value->isFirstClassCallable()) {
            return false;
        }

        return $argument->value->name instanceof Identifier
            && $argument->value->name->toString() === 'handle';
    }

    private function hasTransactionalAttribute(MethodCall $handlerCall, Scope $scope): bool
    {
        if (!$handlerCall->name instanceof Identifier) {
            return false;
        }

        $methodReflection = $scope->getMethodReflection(
            typeWithMethod: $scope->getType($handlerCall->var),
            methodName: $handlerCall->name->toString(),
        );
        if ($methodReflection === null) {
            return false;
        }

        return $methodReflection
            ->getDeclaringClass()
            ->getNativeReflection()
            ->getMethod($methodReflection->getName())
            ->getAttributes(Transactional::class) !== [];
    }
}
