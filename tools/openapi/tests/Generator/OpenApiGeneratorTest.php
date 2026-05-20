<?php

declare(strict_types=1);

namespace Tools\OpenApi\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Spiral\Translator\TranslatorInterface;
use Symfony\Component\Yaml\Yaml;
use Tools\OpenApi\Config\OpenApiGeneratorConfig;
use Tools\OpenApi\Config\ResponseWrapperMapping;
use Tools\OpenApi\OpenApiGenerator;
use Tools\OpenApi\Response\CollectionResponse;
use Tools\OpenApi\Response\DataResponse;
use Tools\OpenApi\Response\ErrorResponse;
use Tools\OpenApi\Response\PaginationResponse;
use Tools\OpenApi\Tests\Support\FakeTranslator;

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

        $result = new OpenApiGenerator(
            translator: self::englishTranslator(),
        )->generate($this->generatorConfig(outputFile: $outputFile));

        self::assertSame(4, $result->operationCount);
        self::assertFileExists($outputFile);

        $spec = Yaml::parseFile($outputFile);

        self::assertIsArray($spec);
        $this->assertGeneratedSpec(
            spec: $spec,
            successDescription: 'Successful response.',
            errorDescription: 'API error.',
        );
    }

    public function testFixtureProjectGeneratesRussianResponseDescriptions(): void
    {
        $outputFile = __DIR__ . '/../../runtime/openapi-fixture-ru.yml';

        $result = new OpenApiGenerator(
            translator: new FakeTranslator(
                locale: 'ru',
                messages: [
                    'yoga_loka.openapi.successful_response' => 'Успешный ответ.',
                    'yoga_loka.openapi.api_error' => 'Ошибка API.',
                ],
            ),
        )->generate($this->generatorConfig(outputFile: $outputFile));

        self::assertSame(4, $result->operationCount);
        self::assertFileExists($outputFile);

        $spec = Yaml::parseFile($outputFile);

        self::assertIsArray($spec);
        $this->assertGeneratedSpec(
            spec: $spec,
            successDescription: 'Успешный ответ.',
            errorDescription: 'Ошибка API.',
        );
    }

    private function generatorConfig(string $outputFile): OpenApiGeneratorConfig
    {
        return new OpenApiGeneratorConfig(
            projectRoot: __DIR__ . '/../..',
            sourcePaths: [__DIR__ . '/../Fixtures/Endpoint/Api/V1'],
            apiNamespace: 'Tools\\OpenApi\\Tests\\Fixtures\\Endpoint\\Api\\V1',
            routePrefix: '/api/v1',
            outputFile: $outputFile,
            title: 'Fixture API',
            version: '1.0.0',
            responseWrapperMapping: new ResponseWrapperMapping(
                dataResponseClass: DataResponse::class,
                collectionResponseClass: CollectionResponse::class,
                paginationResponseClass: PaginationResponse::class,
                errorResponseClass: ErrorResponse::class,
            ),
        );
    }

    private static function englishTranslator(): FakeTranslator
    {
        return new FakeTranslator(
            locale: 'en',
            messages: [
                'yoga_loka.openapi.successful_response' => 'Successful response.',
                'yoga_loka.openapi.api_error' => 'API error.',
            ],
        );
    }

    /**
     * @param array<mixed> $spec
     */
    private function assertGeneratedSpec(array $spec, string $successDescription, string $errorDescription): void
    {
        self::assertSame('3.1.0', $spec['openapi'] ?? null);

        $servers = $this->arrayValue($spec, 'servers');
        $firstServer = $this->arrayValue($servers, 0);
        $paths = $this->arrayValue($spec, 'paths');
        $healthPath = $this->arrayValue($paths, '/health');
        $healthGet = $this->arrayValue($healthPath, 'get');
        $usersPath = $this->arrayValue($paths, '/users');
        $usersGet = $this->arrayValue($usersPath, 'get');
        $usersParameters = $this->arrayValue($usersGet, 'parameters');
        $firstUsersParameter = $this->arrayValue($usersParameters, 0);
        $openApiExportPath = $this->arrayValue($paths, '/export/openapi.yml');
        $openApiExportGet = $this->arrayValue($openApiExportPath, 'get');
        $reportExportPath = $this->arrayValue($paths, '/export/report.pdf');
        $reportExportGet = $this->arrayValue($reportExportPath, 'get');
        $components = $this->arrayValue($spec, 'components');
        $schemas = $this->arrayValue($components, 'schemas');

        self::assertSame('/api/v1', $firstServer['url'] ?? null);
        self::assertSame('health', $healthGet['operationId'] ?? null);
        self::assertSame('api_v1_users_search', $usersGet['operationId'] ?? null);
        self::assertSame('query', $firstUsersParameter['name'] ?? null);
        $this->assertStandardResponseDescriptions($healthGet, $successDescription, $errorDescription);
        $this->assertStandardResponseDescriptions($usersGet, $successDescription, $errorDescription);
        $this->assertStandardResponseDescriptions($openApiExportGet, $successDescription, $errorDescription);
        $this->assertStandardResponseDescriptions($reportExportGet, $successDescription, $errorDescription);
        $this->assertFileContentResponse($openApiExportGet);
        $this->assertFileResponse($reportExportGet);
        self::assertArrayHasKey('HealthResource', $schemas);
        self::assertArrayHasKey('UserResource', $schemas);
        self::assertArrayHasKey('ErrorResponse', $schemas);
        self::assertArrayNotHasKey('/internal-docs', $paths);
    }

    /**
     * @param array<mixed> $operation
     */
    private function assertStandardResponseDescriptions(
        array $operation,
        string $successDescription,
        string $errorDescription,
    ): void {
        $responses = $this->arrayValue($operation, 'responses');
        $successResponse = $this->arrayValue($responses, 200);
        $errorResponse = $this->arrayValue($responses, 'default');

        self::assertSame($successDescription, $successResponse['description'] ?? null);
        self::assertSame($errorDescription, $errorResponse['description'] ?? null);
    }

    /**
     * @param array<mixed> $operation
     */
    private function assertFileContentResponse(array $operation): void
    {
        $responses = $this->arrayValue($operation, 'responses');
        $successResponse = $this->arrayValue($responses, 200);
        $content = $this->arrayValue($successResponse, 'content');
        $yamlContent = $this->arrayValue($content, 'application/yaml');
        $schema = $this->arrayValue($yamlContent, 'schema');

        self::assertSame('api_v1_export_openapi', $operation['operationId'] ?? null);
        self::assertSame('string', $schema['type'] ?? null);
        self::assertArrayNotHasKey('format', $schema);
    }

    /**
     * @param array<mixed> $operation
     */
    private function assertFileResponse(array $operation): void
    {
        $responses = $this->arrayValue($operation, 'responses');
        $successResponse = $this->arrayValue($responses, 200);
        $content = $this->arrayValue($successResponse, 'content');
        $pdfContent = $this->arrayValue($content, 'application/pdf');
        $schema = $this->arrayValue($pdfContent, 'schema');

        self::assertSame('api_v1_export_report', $operation['operationId'] ?? null);
        self::assertSame('string', $schema['type'] ?? null);
        self::assertSame('binary', $schema['format'] ?? null);
    }

    /**
     * @param array<mixed> $values
     * @return array<mixed>
     */
    private function arrayValue(array $values, int|string $key): array
    {
        self::assertArrayHasKey($key, $values);

        $value = $values[$key];
        self::assertIsArray($value);

        return $value;
    }
}
