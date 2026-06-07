<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Schema;

use GianTiaga\SpiralOpenApi\Exception\OpenApiGenerationException;
use GianTiaga\SpiralOpenApi\Model\ClassMetadata;
use GianTiaga\SpiralOpenApi\Model\PropertyMetadata;

final readonly class SchemaBuilder
{
    /**
     * @param array<string, ClassMetadata> $classesByName
     * @param array<string, ClassMetadata> $classesByShortName
     */
    public function __construct(private array $classesByName, private array $classesByShortName, private SchemaRegistry $schemaRegistry) {}
    /**
     * @return array<string, mixed>
     */
    public function referenceFor(string $className): array
    {
        $classMetadata = $this->resolveClass($className);
        $this->ensureClassSchema($classMetadata);
        return ['$ref' => \sprintf('#/components/schemas/%s', $classMetadata->shortName)];
    }
    public function ensureClassSchema(ClassMetadata $classMetadata): void
    {
        if ($this->schemaRegistry->has($classMetadata->shortName)) {
            return;
        }
        if ($classMetadata->enum) {
            $this->schemaRegistry->add(schemaName: $classMetadata->shortName, schema: ['type' => 'string', 'enum' => $classMetadata->enumCases]);
            return;
        }
        $this->schemaRegistry->add(schemaName: $classMetadata->shortName, schema: ['type' => 'object', 'properties' => new \stdClass()]);
        $properties = [];
        $required = [];
        foreach ($classMetadata->properties as $propertyMetadata) {
            $properties[$propertyMetadata->name] = $this->schemaForProperty($propertyMetadata);
            if ($propertyMetadata->isRequired()) {
                $required[] = $propertyMetadata->name;
            }
        }
        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        $this->schemaRegistry->add(schemaName: $classMetadata->shortName, schema: $schema);
    }
    /**
     * @return array<string, mixed>
     */
    public function schemaForProperty(PropertyMetadata $propertyMetadata): array
    {
        if ($propertyMetadata->listItemType !== null) {
            return ['type' => 'array', 'items' => $this->schemaForType($propertyMetadata->listItemType)];
        }
        $schema = $this->schemaForType($propertyMetadata->type);
        if ($propertyMetadata->nullable) {
            $schema['nullable'] = true;
        }
        return $schema;
    }
    /**
     * @return array<string, mixed>
     */
    public function schemaForType(string $type): array
    {
        $baseType = \ltrim(string: $type, characters: '\\');
        return match ($baseType) {
            'string' => ['type' => 'string'],
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            default => $this->referenceFor($baseType),
        };
    }
    private function resolveClass(string $className): ClassMetadata
    {
        $normalizedClassName = \ltrim(string: $className, characters: '\\');
        if (isset($this->classesByName[$normalizedClassName])) {
            return $this->classesByName[$normalizedClassName];
        }
        if (isset($this->classesByShortName[$normalizedClassName])) {
            return $this->classesByShortName[$normalizedClassName];
        }
        throw new OpenApiGenerationException(\sprintf('Неизвестный тип для OpenAPI-схемы: %s.', $className));
    }
}
