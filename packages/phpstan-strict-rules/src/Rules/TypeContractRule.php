<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Rules;

use GianTiaga\PhpStanStrictRules\TypeContracts\PhpDocContractTypeCollector;
use GianTiaga\PhpStanStrictRules\TypeContracts\TypeContractInspector;
use GianTiaga\PhpStanStrictRules\TypeContracts\TypeContractViolation;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\FileTypeMapper;

/**
 * @implements Rule<Node>
 */
final class TypeContractRule implements Rule
{
    public function __construct(
        private readonly TypeContractInspector $inspector,
        private readonly FileTypeMapper $fileTypeMapper,
        private readonly PhpDocContractTypeCollector $phpDocContractTypeCollector,
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
        $errors = $this->inspectPhpDoc(node: $node, scope: $scope);

        if (!$node instanceof ClassMethod && !$node instanceof Function_ && !$node instanceof Closure && !$node instanceof ArrowFunction && !$node instanceof Property) {
            return $errors;
        }

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function inspectPhpDoc(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassLike && !$node instanceof ClassMethod && !$node instanceof Function_ && !$node instanceof Property && !$node instanceof Expression) {
            return [];
        }

        $docComment = $node->getDocComment();
        if (!$docComment instanceof Doc) {
            return [];
        }

        $className = $this->resolveClassName(node: $node, scope: $scope);
        $traitReflection = $scope->getTraitReflection();
        $phpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
            fileName: $scope->getFile(),
            className: $className,
            traitName: $traitReflection?->getName(),
            functionName: $scope->getFunctionName(),
            docComment: $docComment->getText(),
        );

        $errors = [];
        foreach ($this->phpDocContractTypeCollector->collect($phpDoc) as $type) {
            foreach ($this->inspector->inspect($type) as $violation) {
                $errors[] = $this->buildError(violation: $violation, line: $docComment->getStartLine());
            }
        }

        return $this->uniqueErrors($errors);
    }

    private function resolveClassName(Node $node, Scope $scope): ?string
    {
        if ($node instanceof ClassLike && $node->namespacedName instanceof Name) {
            return $node->namespacedName->toString();
        }

        return $scope->getClassReflection()?->getName();
    }

    private function buildError(TypeContractViolation $violation, int $line): RuleError
    {
        return RuleErrorBuilder::message($violation->message())
            ->identifier($violation->identifier())
            ->line($line)
            ->build();
    }

    /**
     * @param list<RuleError> $errors
     * @return list<RuleError>
     */
    private function uniqueErrors(array $errors): array
    {
        $unique = [];
        $seen = [];

        foreach ($errors as $error) {
            $message = $error->getMessage();
            if (\in_array(needle: $message, haystack: $seen, strict: true)) {
                continue;
            }

            $seen[] = $message;
            $unique[] = $error;
        }

        return $unique;
    }
}
