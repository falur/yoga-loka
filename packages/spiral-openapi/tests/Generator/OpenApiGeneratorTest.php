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
        (new OpenApiGenerator(translator: self::englishTranslator()))->generate(new OpenApiGeneratorConfig(projectRoot: __DIR__ . '/../..', sourcePaths: [], apiNamespace: 'GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1', routePrefix: '/api/v1', outputFile: __DIR__ . '/../../runtime/openapi-invalid.yml', title: 'Fixture API', version: '1.0.0', responseWrapperMapping: new ResponseWrapperMapping(dataResponseClass: DataResponse::class, collectionResponseClass: CollectionResponse::class, paginationResponseClass: PaginationResponse::class, errorResponseClass: ErrorResponse::class)));
    }
    private function generatorConfig(string $outputFile): OpenApiGeneratorConfig
    {
        return new OpenApiGeneratorConfig(projectRoot: __DIR__ . '/../..', sourcePaths: [__DIR__ . '/../Fixtures/Endpoint/Api/V1'], apiNamespace: 'GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1', routePrefix: '/api/v1', outputFile: $outputFile, title: 'Fixture API', version: '1.0.0', responseWrapperMapping: new ResponseWrapperMapping(dataResponseClass: DataResponse::class, collectionResponseClass: CollectionResponse::class, paginationResponseClass: PaginationResponse::class, errorResponseClass: ErrorResponse::class));
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
