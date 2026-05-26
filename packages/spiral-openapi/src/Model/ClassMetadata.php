<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Model;

final readonly class ClassMetadata
{
    /**
     * @param list<MethodMetadata> $methods
     * @param list<PropertyMetadata> $properties
     * @param list<string> $enumCases
     */
    public function __construct(public string $filePath, public string $className, public string $shortName, public ?string $parentClass, public bool $enum, public array $methods, public array $properties, public array $enumCases)
    {
    }
}
