<?php

declare(strict_types=1);

namespace Tools\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
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

    private ?string $currentFile = null;

    /**
     * PHPStan can visit the same nullsafe-call arguments more than once during analysis.
     *
     * @var array<int, true>
     */
    private array $reportedArguments = [];

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
}
