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

        // Публичный контракт: media — массив объектов MediaResource (общая форма из Shared), а не
        // массив строк и не промежуточная обёртка.
        self::assertSame('array', $properties['media']['type'] ?? null);
        self::assertSame(
            ['$ref' => '#/components/schemas/MediaResource'],
            $properties['media']['items'] ?? null,
        );

        // Публичный контракт: tags — массив объектов TagResource, а не массив строк.
        self::assertSame('array', $properties['tags']['type'] ?? null);
        self::assertSame(
            ['$ref' => '#/components/schemas/TagResource'],
            $properties['tags']['items'] ?? null,
        );

        // Сами объектные схемы присутствуют в спецификации.
        self::assertArrayHasKey('TagResource', $schemas);
        self::assertArrayHasKey('MediaResource', $schemas);
        self::assertArrayHasKey('MediaOriginalResource', $schemas);
        self::assertArrayHasKey('MediaConversionResource', $schemas);

        // Публичный контракт: аватар автора — тот же объект MediaResource или null (аватара нет,
        // заглушку рисует клиент), поэтому свойство — nullable-объект (oneOf с null).
        $author = $schemas['AuthorResource']['properties'] ?? null;
        self::assertIsArray($author);
        self::assertSame(
            [['$ref' => '#/components/schemas/MediaResource'], ['type' => 'null']],
            $author['avatar']['oneOf'] ?? null,
        );

        // Публичный контракт: медиа = id (всегда задан — объект есть только за реальным медиа) и
        // позиция (nullable — неприменимое поле null), оригинал (nullable-объект, null если оригинал
        // удалён) + набор конверсий (массив объектов MediaConversionResource).
        $media = $schemas['MediaResource']['properties'] ?? null;
        self::assertIsArray($media);
        self::assertSame('string', $media['id']['type'] ?? null);
        self::assertSame(['integer', 'null'], $media['position']['type'] ?? null);
        self::assertSame(
            [['$ref' => '#/components/schemas/MediaOriginalResource'], ['type' => 'null']],
            $media['original']['oneOf'] ?? null,
        );
        self::assertSame('array', $media['conversions']['type'] ?? null);
        self::assertSame(
            ['$ref' => '#/components/schemas/MediaConversionResource'],
            $media['conversions']['items'] ?? null,
        );

        // Публичный контракт: вид/тип конверсии — enum-ы (закрытый набор), а не свободные строки.
        // kind ссылается на enum-схему, type — union трёх enum-ов через oneOf.
        $conversion = $schemas['MediaConversionResource']['properties'] ?? null;
        self::assertIsArray($conversion);
        self::assertSame(['$ref' => '#/components/schemas/MediaConversionKind'], $conversion['kind'] ?? null);
        self::assertSame(
            [
                ['$ref' => '#/components/schemas/MediaImageConversionType'],
                ['$ref' => '#/components/schemas/MediaVideoConversionType'],
                ['$ref' => '#/components/schemas/MediaAudioConversionType'],
            ],
            $conversion['type']['oneOf'] ?? null,
        );
        self::assertArrayHasKey('MediaConversionKind', $schemas);
    }
}
