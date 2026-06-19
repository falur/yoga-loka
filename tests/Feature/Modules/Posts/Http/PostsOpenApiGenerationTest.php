<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use Spiral\Testing\Attribute\Config;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class PostsOpenApiGenerationTest extends TestCase
{
    #[Config('openapi.outputFile', 'runtime/openapi-posts-schema-test.yml')]
    public function testPostResourceMediaAndTagsReferenceObjectSchemas(): void
    {
        $this->runCommand(command: 'openapi:generate');

        $spec = Yaml::parseFile($this->rootDirectory() . '/runtime/openapi-posts-schema-test.yml');

        self::assertIsArray($spec);
        $schemas = $spec['components']['schemas'] ?? null;
        self::assertIsArray($schemas);

        $postResource = $schemas['PostResource'] ?? null;
        self::assertIsArray($postResource);
        $properties = $postResource['properties'] ?? null;
        self::assertIsArray($properties);

        // Публичный контракт: media — массив объектов PostMediaItemResource, а не массив строк.
        self::assertSame('array', $properties['media']['type'] ?? null);
        self::assertSame(
            ['$ref' => '#/components/schemas/PostMediaItemResource'],
            $properties['media']['items'] ?? null,
        );

        // Публичный контракт: tags — массив объектов TagResource, а не массив строк.
        self::assertSame('array', $properties['tags']['type'] ?? null);
        self::assertSame(
            ['$ref' => '#/components/schemas/TagResource'],
            $properties['tags']['items'] ?? null,
        );

        // Сами объектные схемы присутствуют в спецификации.
        self::assertArrayHasKey('PostMediaItemResource', $schemas);
        self::assertArrayHasKey('TagResource', $schemas);
    }
}
