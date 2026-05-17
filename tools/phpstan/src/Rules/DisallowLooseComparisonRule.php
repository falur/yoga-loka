<?php

declare(strict_types=1);

namespace Tools\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<BinaryOp>
 */
final class DisallowLooseComparisonRule implements Rule
{
    private const string LOOSE_EQUAL_MESSAGE = 'Loose comparison with ' . '=' . '=' . ' is forbidden. Use ' . '=' . '=' . '=' . ' instead.';
    private const string LOOSE_EQUAL_IDENTIFIER = 'project.looseEqualForbidden';
    private const string LOOSE_NOT_EQUAL_MESSAGE = 'Loose not-equal comparison is forbidden. Use !== instead.';
    private const string LOOSE_NOT_EQUAL_IDENTIFIER = 'project.looseNotEqualForbidden';

    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Equal) {
            return [
                RuleErrorBuilder::message(self::LOOSE_EQUAL_MESSAGE)
                    ->identifier(self::LOOSE_EQUAL_IDENTIFIER)
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        if ($node instanceof NotEqual) {
            return [
                RuleErrorBuilder::message(self::LOOSE_NOT_EQUAL_MESSAGE)
                    ->identifier(self::LOOSE_NOT_EQUAL_IDENTIFIER)
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
