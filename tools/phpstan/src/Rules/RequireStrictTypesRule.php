<?php

declare(strict_types=1);

namespace Tools\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Declare_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FileNode>
 */
final class RequireStrictTypesRule implements Rule
{
    private const string ERROR_MESSAGE = 'Every analysed PHP file must start with declare(strict_types=1).';
    private const string ERROR_IDENTIFIER = 'project.missingStrictTypes';

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $nodes = $node->getNodes();
        $firstNode = $nodes[0] ?? null;

        if ($firstNode instanceof Declare_ && $this->hasStrictTypesEnabled($firstNode)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier(self::ERROR_IDENTIFIER)
                ->line(1)
                ->build(),
        ];
    }

    private function hasStrictTypesEnabled(Declare_ $declare): bool
    {
        foreach ($declare->declares as $declareItem) {
            if ($declareItem->key->toString() !== 'strict_types') {
                continue;
            }

            return $declareItem->value instanceof Int_ && $declareItem->value->value === 1;
        }

        return false;
    }
}
