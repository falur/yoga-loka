<?php

declare(strict_types=1);

namespace Tools\PHPStan\TypeContracts;

use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\PhpDoc\Tag\MethodTagParameter;
use PHPStan\PhpDoc\Tag\TypeAliasTag;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\Type\Type;

final class PhpDocContractTypeCollector
{
    public function __construct(
        private readonly TypeNodeResolver $typeNodeResolver,
    ) {}

    /**
     * @return list<Type>
     */
    public function collect(ResolvedPhpDocBlock $phpDoc): array
    {
        $types = [];

        foreach ($phpDoc->getVarTags() as $tag) {
            $types[] = $tag->getType();
        }

        foreach ($phpDoc->getParamTags() as $tag) {
            $types[] = $tag->getType();
        }

        foreach ($phpDoc->getParamOutTags() as $tag) {
            $types[] = $tag->getType();
        }

        $returnTag = $phpDoc->getReturnTag();
        if ($returnTag !== null) {
            $types[] = $returnTag->getType();
        }

        $throwsTag = $phpDoc->getThrowsTag();
        if ($throwsTag !== null) {
            $types[] = $throwsTag->getType();
        }

        foreach ($phpDoc->getTemplateTags() as $tag) {
            $types[] = $tag->getBound();

            $default = $tag->getDefault();
            if ($default !== null) {
                $types[] = $default;
            }
        }

        foreach ($phpDoc->getTypeAliasTags() as $tag) {
            $types[] = $this->resolveTypeAlias($tag);
        }

        foreach ($phpDoc->getPropertyTags() as $tag) {
            $readableType = $tag->getReadableType();
            if ($readableType !== null) {
                $types[] = $readableType;
            }

            $writableType = $tag->getWritableType();
            if ($writableType !== null) {
                $types[] = $writableType;
            }
        }

        foreach ($phpDoc->getMethodTags() as $tag) {
            $types[] = $tag->getReturnType();

            foreach ($tag->getParameters() as $parameter) {
                /** @var MethodTagParameter $parameter */
                $types[] = $parameter->getType();

                $defaultValue = $parameter->getDefaultValue();
                if ($defaultValue !== null) {
                    $types[] = $defaultValue;
                }
            }

            foreach ($tag->getTemplateTags() as $templateTag) {
                $types[] = $templateTag->getBound();

                $default = $templateTag->getDefault();
                if ($default !== null) {
                    $types[] = $default;
                }
            }
        }

        return $types;
    }

    private function resolveTypeAlias(TypeAliasTag $tag): Type
    {
        $typeNodeResolver = $this->typeNodeResolver;
        $resolve = \Closure::bind(
            closure: static fn(TypeAliasTag $aliasTag): Type => $typeNodeResolver->resolve(typeNode: $aliasTag->typeNode, nameScope: $aliasTag->nameScope),
            newThis: null,
            newScope: TypeAliasTag::class,
        );

        return $resolve($tag);
    }
}
