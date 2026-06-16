<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Schema;

/**
 * Выражает обнуляемость схемы способом, валидным для объявленной версии OpenAPI.
 *
 * В OpenAPI 3.1 (JSON Schema 2020-12) ключевое слово `nullable` удалено из стандарта:
 * обнуляемость выражается типом-объединением `type: [<type>, "null"]` для скаляров и массивов
 * и `oneOf: [<схема>, {type: "null"}]` для ссылок и составных схем. В OpenAPI 3.0 обнуляемость
 * выражается ключом `nullable: true`, но рядом с `$ref` он игнорируется, поэтому ссылка
 * оборачивается в `allOf`.
 */
final readonly class NullableSchema
{
    private const NULL_TYPE = 'null';

    public function __construct(private string $openApiVersion) {}

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public function makeNullable(array $schema): array
    {
        if ($this->isOpenApi31()) {
            return $this->makeNullableForOpenApi31(schema: $schema);
        }

        return $this->makeNullableForOpenApi30(schema: $schema);
    }

    private function isOpenApi31(): bool
    {
        return \str_starts_with(haystack: $this->openApiVersion, needle: '3.1');
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function makeNullableForOpenApi31(array $schema): array
    {
        $type = $schema['type'] ?? null;
        if (\is_string($type)) {
            $schema['type'] = [$type, self::NULL_TYPE];

            return $schema;
        }

        return ['oneOf' => [$schema, ['type' => self::NULL_TYPE]]];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function makeNullableForOpenApi30(array $schema): array
    {
        if (isset($schema['$ref'])) {
            return ['allOf' => [$schema], 'nullable' => true];
        }

        $schema['nullable'] = true;

        return $schema;
    }
}
