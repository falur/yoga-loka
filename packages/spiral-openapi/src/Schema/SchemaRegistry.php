<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Schema;

final class SchemaRegistry
{
    /** @var array<string, mixed> */
    private array $schemas = [];
    public function has(string $schemaName): bool
    {
        return isset($this->schemas[$schemaName]);
    }
    /**
     * @param array<string, mixed> $schema
     */
    public function add(string $schemaName, array $schema): void
    {
        $this->schemas[$schemaName] = $schema;
    }
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        \ksort($this->schemas);
        return $this->schemas;
    }
    public function count(): int
    {
        return \count($this->schemas);
    }
}
