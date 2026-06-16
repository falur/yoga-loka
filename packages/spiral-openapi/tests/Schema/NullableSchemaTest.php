<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Schema;

use PHPUnit\Framework\TestCase;
use GianTiaga\SpiralOpenApi\Schema\NullableSchema;

final class NullableSchemaTest extends TestCase
{
    public function testOpenApi31ExpressesScalarNullabilityViaTypeUnion(): void
    {
        $nullableSchema = new NullableSchema(openApiVersion: '3.1.0');
        self::assertSame(
            ['type' => ['string', 'null']],
            $nullableSchema->makeNullable(['type' => 'string']),
        );
    }

    public function testOpenApi31ExpressesArrayNullabilityViaTypeUnionKeepingItems(): void
    {
        $nullableSchema = new NullableSchema(openApiVersion: '3.1.0');
        self::assertSame(
            ['type' => ['array', 'null'], 'items' => ['type' => 'string']],
            $nullableSchema->makeNullable(['type' => 'array', 'items' => ['type' => 'string']]),
        );
    }

    public function testOpenApi31ExpressesReferenceNullabilityViaOneOf(): void
    {
        $nullableSchema = new NullableSchema(openApiVersion: '3.1.0');
        self::assertSame(
            ['oneOf' => [['$ref' => '#/components/schemas/HealthResource'], ['type' => 'null']]],
            $nullableSchema->makeNullable(['$ref' => '#/components/schemas/HealthResource']),
        );
    }

    public function testOpenApi30ExpressesScalarNullabilityViaNullableKey(): void
    {
        $nullableSchema = new NullableSchema(openApiVersion: '3.0.3');
        self::assertSame(
            ['type' => 'string', 'nullable' => true],
            $nullableSchema->makeNullable(['type' => 'string']),
        );
    }

    public function testOpenApi30WrapsReferenceInAllOfToAvoidIgnoredNullableSibling(): void
    {
        $nullableSchema = new NullableSchema(openApiVersion: '3.0.3');
        self::assertSame(
            ['allOf' => [['$ref' => '#/components/schemas/HealthResource']], 'nullable' => true],
            $nullableSchema->makeNullable(['$ref' => '#/components/schemas/HealthResource']),
        );
    }
}
