<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Spiral\Translator\TranslatorInterface;
use Symfony\Component\Yaml\Yaml;
use GianTiaga\SpiralOpenApi\Config\OpenApiGeneratorConfig;
use GianTiaga\SpiralOpenApi\Config\ResponseWrapperMapping;
use GianTiaga\SpiralOpenApi\Exception\OpenApiConfigurationException;
use GianTiaga\SpiralOpenApi\OpenApiGenerator;
use GianTiaga\SpiralOpenApi\Response\CollectionResponse;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
use GianTiaga\SpiralOpenApi\Tests\Support\FakeTranslator;

final class OpenApiGeneratorTest extends TestCase
{
    public function testGeneratorRequiresTranslator(): void
    {
        $constructor = (new \ReflectionClass(OpenApiGenerator::class))->getConstructor();
        self::assertNotNull($constructor);
        $parameter = $constructor->getParameters()[0] ?? null;
        self::assertNotNull($parameter);
        self::assertSame('translator', $parameter->getName());
        self::assertFalse($parameter->isOptional());
        $type = $parameter->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(TranslatorInterface::class, $type->getName());
    }
    public function testFixtureProjectGeneratesOpenApiYaml(): void
    {
        $outputFile = __DIR__ . '/../../runtime/openapi-fixture.yml';
        $result = (new OpenApiGenerator(translator: self::englishTranslator()))->generate($this->generatorConfig(outputFile: $outputFile));
        self::assertSame(4, $result->operationCount);
        self::assertFileExists($outputFile);
        $spec = Yaml::parseFile($outputFile);
        self::assertIsArray($spec);
        $this->assertGeneratedSpec(spec: new OpenApiSpecNode(value: $spec), successDescription: 'Successful response.', errorDescription: 'API error.');
    }
    public function testFixtureProjectGeneratesRussianResponseDescriptions(): void
    {
        $outputFile = __DIR__ . '/../../runtime/openapi-fixture-ru.yml';
        $result = (new OpenApiGenerator(translator: new FakeTranslator(locale: 'ru', messages: ['gian_tiaga.spiral_openapi.successful_response' => 'Успешный ответ.', 'gian_tiaga.spiral_openapi.api_error' => 'Ошибка API.'])))->generate($this->generatorConfig(outputFile: $outputFile));
        self::assertSame(4, $result->operationCount);
        self::assertFileExists($outputFile);
        $spec = Yaml::parseFile($outputFile);
        self::assertIsArray($spec);
        $this->assertGeneratedSpec(spec: new OpenApiSpecNode(value: $spec), successDescription: 'Успешный ответ.', errorDescription: 'Ошибка API.');
    }
    public function testConfigurationExceptionStaysRussianWithEnglishLocale(): void
    {
        $this->expectException(OpenApiConfigurationException::class);
        $this->expectExceptionMessage('Не указан ни один каталог исходного кода API.');
        (new OpenApiGenerator(translator: self::englishTranslator()))->generate(new OpenApiGeneratorConfig(projectRoot: __DIR__ . '/../..', sourcePaths: [], apiNamespace: 'GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1', routePrefix: '/api/v1', outputFile: __DIR__ . '/../../runtime/openapi-invalid.yml', title: 'Fixture API', version: '1.0.0', responseWrapperMapping: new ResponseWrapperMapping(dataResponseClass: DataResponse::class, collectionResponseClass: CollectionResponse::class, paginationResponseClass: PaginationResponse::class, errorResponseClass: ErrorResponse::class, emptyResponseClass: EmptySuccessResponse::class)));
    }
    public function testFixtureProjectGeneratesOpenApi30NullableForms(): void
    {
        $outputFile = __DIR__ . '/../../runtime/openapi-fixture-30.yml';
        $result = (new OpenApiGenerator(translator: self::englishTranslator()))->generate($this->generatorConfig(outputFile: $outputFile, openApiVersion: '3.0.3'));
        self::assertSame(4, $result->operationCount);
        self::assertFileExists($outputFile);
        $spec = Yaml::parseFile($outputFile);
        self::assertIsArray($spec);
        $specNode = new OpenApiSpecNode(value: $spec);
        self::assertSame('3.0.3', $specNode->value(key: 'openapi'));
        $schemas = $specNode->child(key: 'components')->child(key: 'schemas');
        self::assertTrue($schemas->has(key: 'UserResource'));
        $this->assertUserResourceNullableSchemaForOpenApi30(schemas: $schemas);
    }
    public function testEmptySuccessResponseGeneratesNoContentOperation(): void
    {
        $outputFile = __DIR__ . '/../../runtime/openapi-fixture-no-content.yml';
        // Метод без @return-дженерика не должен бросать OpenApiGenerationException: ветка 204 перехватывает его раньше.
        $result = (new OpenApiGenerator(translator: self::englishTranslator()))->generate(new OpenApiGeneratorConfig(projectRoot: __DIR__ . '/../..', sourcePaths: [__DIR__ . '/../Fixtures/Endpoint/NoContent/Api/V1'], apiNamespace: 'GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\NoContent\Api\V1', routePrefix: '/api/v1', outputFile: $outputFile, title: 'Fixture API', version: '1.0.0', responseWrapperMapping: new ResponseWrapperMapping(dataResponseClass: DataResponse::class, collectionResponseClass: CollectionResponse::class, paginationResponseClass: PaginationResponse::class, errorResponseClass: ErrorResponse::class, emptyResponseClass: EmptySuccessResponse::class)));
        self::assertSame(1, $result->operationCount);
        self::assertFileExists($outputFile);
        $spec = Yaml::parseFile($outputFile);
        self::assertIsArray($spec);
        $specNode = new OpenApiSpecNode(value: $spec);
        $operation = $specNode->child(key: 'paths')->child(key: '/commands/run')->child(key: 'post');
        self::assertSame('api_v1_commands_run', $operation->value(key: 'operationId'));
        $responses = $operation->child(key: 'responses');
        self::assertTrue($responses->has(key: 204));
        self::assertFalse($responses->has(key: 200));
        $noContentResponse = $responses->child(key: 204);
        self::assertSame('Successful response.', $noContentResponse->value(key: 'description'));
        self::assertFalse($noContentResponse->has(key: 'content'));
        $schemas = $specNode->child(key: 'components')->child(key: 'schemas');
        self::assertFalse($schemas->has(key: 'EmptySuccessResponse'));
    }
    public function testRoutePathParameterIsNormalizedToOpenApiForm(): void
    {
        $outputFile = __DIR__ . '/../../runtime/openapi-fixture-path-param.yml';
        $result = (new OpenApiGenerator(translator: self::englishTranslator()))->generate(new OpenApiGeneratorConfig(projectRoot: __DIR__ . '/../..', sourcePaths: [__DIR__ . '/../Fixtures/Endpoint/PathParam/Api/V1'], apiNamespace: 'GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\PathParam\Api\V1', routePrefix: '/api/v1', outputFile: $outputFile, title: 'Fixture API', version: '1.0.0', responseWrapperMapping: new ResponseWrapperMapping(dataResponseClass: DataResponse::class, collectionResponseClass: CollectionResponse::class, paginationResponseClass: PaginationResponse::class, errorResponseClass: ErrorResponse::class, emptyResponseClass: EmptySuccessResponse::class)));
        self::assertSame(1, $result->operationCount);
        self::assertFileExists($outputFile);
        $spec = Yaml::parseFile($outputFile);
        self::assertIsArray($spec);
        $specNode = new OpenApiSpecNode(value: $spec);
        $paths = $specNode->child(key: 'paths');
        // Spiral <sessionId> нормализуется в OpenApiForm {sessionId}; старая форма отсутствует.
        self::assertTrue($paths->has(key: '/sessions/{sessionId}'));
        self::assertFalse($paths->has(key: '/sessions/<sessionId>'));
        $operation = $paths->child(key: '/sessions/{sessionId}')->child(key: 'delete');
        $firstParameter = $operation->child(key: 'parameters')->child(key: 0);
        self::assertSame('sessionId', $firstParameter->value(key: 'name'));
        self::assertSame('path', $firstParameter->value(key: 'in'));
        self::assertTrue($firstParameter->value(key: 'required'));
    }
    public function testGeneratorResolvesGlobSourcePaths(): void
    {
        $outputFile = __DIR__ . '/../../runtime/openapi-fixture-glob.yml';
        $result = (new OpenApiGenerator(translator: self::englishTranslator()))->generate($this->generatorConfig(outputFile: $outputFile, sourcePaths: [__DIR__ . '/../Fixtures/*/Api/V1']));
        self::assertSame(4, $result->operationCount);
        self::assertFileExists($outputFile);
    }
    public function testConfigurationExceptionWhenGlobMatchesNoDirectory(): void
    {
        $this->expectException(OpenApiConfigurationException::class);
        $this->expectExceptionMessage('Каталог исходного кода API не найден');
        (new OpenApiGenerator(translator: self::englishTranslator()))->generate($this->generatorConfig(outputFile: __DIR__ . '/../../runtime/openapi-glob-missing.yml', sourcePaths: [__DIR__ . '/../Fixtures/*/Missing/Endpoint']));
    }
    /**
     * @param list<string> $sourcePaths
     */
    private function generatorConfig(string $outputFile, array $sourcePaths = [__DIR__ . '/../Fixtures/Endpoint/Api/V1'], string $openApiVersion = '3.1.0'): OpenApiGeneratorConfig
    {
        return new OpenApiGeneratorConfig(projectRoot: __DIR__ . '/../..', sourcePaths: $sourcePaths, apiNamespace: 'GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1', routePrefix: '/api/v1', outputFile: $outputFile, title: 'Fixture API', version: '1.0.0', responseWrapperMapping: new ResponseWrapperMapping(dataResponseClass: DataResponse::class, collectionResponseClass: CollectionResponse::class, paginationResponseClass: PaginationResponse::class, errorResponseClass: ErrorResponse::class, emptyResponseClass: EmptySuccessResponse::class), openApiVersion: $openApiVersion);
    }
    private static function englishTranslator(): FakeTranslator
    {
        return new FakeTranslator(locale: 'en', messages: ['gian_tiaga.spiral_openapi.successful_response' => 'Successful response.', 'gian_tiaga.spiral_openapi.api_error' => 'API error.']);
    }
    private function assertGeneratedSpec(OpenApiSpecNode $spec, string $successDescription, string $errorDescription): void
    {
        self::assertSame('3.1.0', $spec->value(key: 'openapi'));
        $servers = $spec->child(key: 'servers');
        $firstServer = $servers->child(key: 0);
        $paths = $spec->child(key: 'paths');
        $healthPath = $paths->child(key: '/health');
        $healthGet = $healthPath->child(key: 'get');
        $usersPath = $paths->child(key: '/users');
        $usersGet = $usersPath->child(key: 'get');
        $usersParameters = $usersGet->child(key: 'parameters');
        $firstUsersParameter = $usersParameters->child(key: 0);
        $openApiExportPath = $paths->child(key: '/export/openapi.yml');
        $openApiExportGet = $openApiExportPath->child(key: 'get');
        $reportExportPath = $paths->child(key: '/export/report.pdf');
        $reportExportGet = $reportExportPath->child(key: 'get');
        $components = $spec->child(key: 'components');
        $schemas = $components->child(key: 'schemas');
        self::assertSame('/api/v1', $firstServer->value(key: 'url'));
        self::assertSame('health', $healthGet->value(key: 'operationId'));
        self::assertSame('api_v1_users_search', $usersGet->value(key: 'operationId'));
        self::assertSame('query', $firstUsersParameter->value(key: 'name'));
        $this->assertStandardResponseDescriptions(operation: $healthGet, successDescription: $successDescription, errorDescription: $errorDescription);
        $this->assertStandardResponseDescriptions(operation: $usersGet, successDescription: $successDescription, errorDescription: $errorDescription);
        $this->assertStandardResponseDescriptions(operation: $openApiExportGet, successDescription: $successDescription, errorDescription: $errorDescription);
        $this->assertStandardResponseDescriptions(operation: $reportExportGet, successDescription: $successDescription, errorDescription: $errorDescription);
        $this->assertFileContentResponse(operation: $openApiExportGet);
        $this->assertFileResponse(operation: $reportExportGet);
        self::assertTrue($schemas->has(key: 'HealthResource'));
        self::assertTrue($schemas->has(key: 'UserResource'));
        self::assertTrue($schemas->has(key: 'ErrorResponse'));
        self::assertFalse($paths->has(key: '/internal-docs'));
        $this->assertUserResourceNullableSchema(schemas: $schemas);
    }
    private function assertUserResourceNullableSchema(OpenApiSpecNode $schemas): void
    {
        $userResource = $schemas->child(key: 'UserResource');
        $properties = $userResource->child(key: 'properties');
        $nickname = $properties->child(key: 'nickname');
        $health = $properties->child(key: 'health');
        $tags = $properties->child(key: 'tags');
        // OpenAPI 3.1 выражает обнуляемость через тип-объединение и oneOf, ключ nullable удалён из стандарта.
        self::assertFalse($nickname->has(key: 'nullable'));
        self::assertFalse($health->has(key: 'nullable'));
        self::assertFalse($tags->has(key: 'nullable'));
        // Скаляр: type: [string, null].
        self::assertSame(['string', 'null'], $nickname->value(key: 'type'));
        // Список: type: [array, null] с сохранённым items.
        self::assertSame(['array', 'null'], $tags->value(key: 'type'));
        self::assertSame(['type' => 'string'], $tags->value(key: 'items'));
        // Список объектов из promoted-параметра конструктора (@param list<HealthResource>):
        // items ссылается на схему объекта через $ref, а не на { type: string }.
        $healthChecks = $properties->child(key: 'healthChecks');
        self::assertSame('array', $healthChecks->value(key: 'type'));
        self::assertSame(['$ref' => '#/components/schemas/HealthResource'], $healthChecks->value(key: 'items'));
        // Ссылка: oneOf со ссылкой и {type: null}, без соседства nullable с $ref.
        self::assertFalse($health->has(key: 'type'));
        self::assertFalse($health->has(key: '$ref'));
        self::assertSame(
            [['$ref' => '#/components/schemas/HealthResource'], ['type' => 'null']],
            $health->value(key: 'oneOf'),
        );
        // Union enum-ов: свойство выражается через oneOf со ссылками на схему каждого enum-а,
        // а не схлопывается до первого типа. Обе enum-схемы попадают в компоненты.
        $primaryStatus = $properties->child(key: 'primaryStatus');
        self::assertFalse($primaryStatus->has(key: 'type'));
        self::assertFalse($primaryStatus->has(key: '$ref'));
        self::assertSame(
            [['$ref' => '#/components/schemas/HealthStatus'], ['$ref' => '#/components/schemas/AccountStatus']],
            $primaryStatus->value(key: 'oneOf'),
        );
        self::assertTrue($schemas->has(key: 'HealthStatus'));
        self::assertTrue($schemas->has(key: 'AccountStatus'));
        // Дата: DateTimeImmutable выражается как string/date-time, nullable — через тип-объединение 3.1.
        $createdAt = $properties->child(key: 'createdAt');
        self::assertSame('string', $createdAt->value(key: 'type'));
        self::assertSame('date-time', $createdAt->value(key: 'format'));
        $deletedAt = $properties->child(key: 'deletedAt');
        self::assertSame(['string', 'null'], $deletedAt->value(key: 'type'));
        self::assertSame('date-time', $deletedAt->value(key: 'format'));
        $required = $userResource->value(key: 'required');
        self::assertIsArray($required);
        self::assertContains('id', $required);
        self::assertContains('email', $required);
        self::assertContains('primaryStatus', $required);
        self::assertContains('createdAt', $required);
        self::assertNotContains('nickname', $required);
        self::assertNotContains('health', $required);
        self::assertNotContains('tags', $required);
        self::assertNotContains('deletedAt', $required);
    }
    private function assertUserResourceNullableSchemaForOpenApi30(OpenApiSpecNode $schemas): void
    {
        $userResource = $schemas->child(key: 'UserResource');
        $properties = $userResource->child(key: 'properties');
        $nickname = $properties->child(key: 'nickname');
        $health = $properties->child(key: 'health');
        $tags = $properties->child(key: 'tags');
        // OpenAPI 3.0 выражает обнуляемость ключом nullable: true, тип-объединение 3.1 не используется.
        self::assertSame('string', $nickname->value(key: 'type'));
        self::assertTrue($nickname->value(key: 'nullable'));
        // Список: type: array + nullable: true, items сохранены.
        self::assertSame('array', $tags->value(key: 'type'));
        self::assertTrue($tags->value(key: 'nullable'));
        self::assertSame(['type' => 'string'], $tags->value(key: 'items'));
        // Ссылка: $ref оборачивается в allOf, рядом с которым nullable: true не игнорируется.
        self::assertFalse($health->has(key: '$ref'));
        self::assertFalse($health->has(key: 'oneOf'));
        self::assertSame([['$ref' => '#/components/schemas/HealthResource']], $health->value(key: 'allOf'));
        self::assertTrue($health->value(key: 'nullable'));
        // Дата: string/date-time, обнуляемость 3.0 — ключом nullable: true.
        $createdAt = $properties->child(key: 'createdAt');
        self::assertSame('string', $createdAt->value(key: 'type'));
        self::assertSame('date-time', $createdAt->value(key: 'format'));
        $deletedAt = $properties->child(key: 'deletedAt');
        self::assertSame('string', $deletedAt->value(key: 'type'));
        self::assertSame('date-time', $deletedAt->value(key: 'format'));
        self::assertTrue($deletedAt->value(key: 'nullable'));
        $required = $userResource->value(key: 'required');
        self::assertIsArray($required);
        self::assertContains('id', $required);
        self::assertContains('email', $required);
        self::assertNotContains('nickname', $required);
        self::assertNotContains('health', $required);
        self::assertNotContains('tags', $required);
    }
    private function assertStandardResponseDescriptions(OpenApiSpecNode $operation, string $successDescription, string $errorDescription): void
    {
        $responses = $operation->child(key: 'responses');
        $successResponse = $responses->child(key: 200);
        $errorResponse = $responses->child(key: 'default');
        self::assertSame($successDescription, $successResponse->value(key: 'description'));
        self::assertSame($errorDescription, $errorResponse->value(key: 'description'));
    }
    private function assertFileContentResponse(OpenApiSpecNode $operation): void
    {
        $responses = $operation->child(key: 'responses');
        $successResponse = $responses->child(key: 200);
        $content = $successResponse->child(key: 'content');
        $yamlContent = $content->child(key: 'application/yaml');
        $schema = $yamlContent->child(key: 'schema');
        self::assertSame('api_v1_export_openapi', $operation->value(key: 'operationId'));
        self::assertSame('string', $schema->value(key: 'type'));
        self::assertFalse($schema->has(key: 'format'));
    }
    private function assertFileResponse(OpenApiSpecNode $operation): void
    {
        $responses = $operation->child(key: 'responses');
        $successResponse = $responses->child(key: 200);
        $content = $successResponse->child(key: 'content');
        $pdfContent = $content->child(key: 'application/pdf');
        $schema = $pdfContent->child(key: 'schema');
        self::assertSame('api_v1_export_report', $operation->value(key: 'operationId'));
        self::assertSame('string', $schema->value(key: 'type'));
        self::assertSame('binary', $schema->value(key: 'format'));
    }
}

final readonly class OpenApiSpecNode
{
    public function __construct(private mixed $value) {}

    public function child(int|string $key): self
    {
        if (!\is_array($this->value) || !\array_key_exists(key: $key, array: $this->value) || !\is_array($this->value[$key])) {
            throw new \LogicException(message: 'OpenAPI-узел должен быть массивом.');
        }

        return new self(value: $this->value[$key]);
    }

    public function value(int|string $key): mixed
    {
        if (!\is_array($this->value) || !\array_key_exists(key: $key, array: $this->value)) {
            throw new \LogicException(message: 'OpenAPI-значение отсутствует.');
        }

        return $this->value[$key];
    }

    public function has(int|string $key): bool
    {
        return \is_array($this->value) && \array_key_exists(key: $key, array: $this->value);
    }
}
