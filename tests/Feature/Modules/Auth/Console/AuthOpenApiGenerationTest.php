<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Console;

use Spiral\Testing\Attribute\Config;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class AuthOpenApiGenerationTest extends TestCase
{
    #[Config('openapi.outputFile', 'runtime/openapi-auth-test.yml')]
    public function testGeneratesAllAuthRoutes(): void
    {
        $this->runCommand(command: 'openapi:generate');

        $contents = \file_get_contents($this->rootDirectory() . '/runtime/openapi-auth-test.yml');

        self::assertIsString($contents);
        self::assertStringContainsString('/auth/code/request:', $contents);
        self::assertStringContainsString('/auth/code/verify:', $contents);
        self::assertStringContainsString('/auth/register:', $contents);
        self::assertStringContainsString('/auth/refresh:', $contents);
        self::assertStringContainsString('/auth/logout:', $contents);
    }

    #[Config('openapi.outputFile', 'runtime/openapi-auth-schema-test.yml')]
    public function testVerifyResultResourceSchemaMarksNullableFields(): void
    {
        $this->runCommand(command: 'openapi:generate');

        $spec = Yaml::parseFile($this->rootDirectory() . '/runtime/openapi-auth-schema-test.yml');

        self::assertIsArray($spec);
        self::assertSame('3.1.0', $spec['openapi'] ?? null);
        $schema = $spec['components']['schemas']['VerifyResultResource'] ?? null;
        self::assertIsArray($schema);

        $properties = $schema['properties'] ?? null;
        self::assertIsArray($properties);
        // OpenAPI 3.1 удалил ключ nullable: обнуляемость выражается через oneOf для ссылок
        // и тип-объединение [<type>, null] для скаляров.
        self::assertArrayNotHasKey('nullable', $properties['tokens']);
        self::assertArrayNotHasKey('nullable', $properties['registrationTicket']);
        self::assertSame(
            [['$ref' => '#/components/schemas/TokenPairResource'], ['type' => 'null']],
            $properties['tokens']['oneOf'] ?? null,
        );
        self::assertSame(['string', 'null'], $properties['registrationTicket']['type'] ?? null);

        $required = $schema['required'] ?? [];
        self::assertIsArray($required);
        self::assertContains('needsProfile', $required);
        self::assertNotContains('tokens', $required);
        self::assertNotContains('registrationTicket', $required);
    }

    #[Config('openapi.outputFile', 'runtime/openapi-auth-no-content-test.yml')]
    public function testCommandEndpointsReturnNoContentResponses(): void
    {
        $this->runCommand(command: 'openapi:generate');

        $spec = Yaml::parseFile($this->rootDirectory() . '/runtime/openapi-auth-no-content-test.yml');

        self::assertIsArray($spec);

        $requestResponses = $spec['paths']['/auth/code/request']['post']['responses'] ?? null;
        self::assertIsArray($requestResponses);
        self::assertArrayHasKey(204, $requestResponses);
        self::assertArrayNotHasKey(200, $requestResponses);

        $logoutResponses = $spec['paths']['/auth/logout']['post']['responses'] ?? null;
        self::assertIsArray($logoutResponses);
        self::assertArrayHasKey(204, $logoutResponses);
        self::assertArrayNotHasKey(200, $logoutResponses);

        // Защита от того, что 204 ошибочно применился ко всем эндпоинтам: соседний verify сохраняет 200 с телом.
        $verifyResponses = $spec['paths']['/auth/code/verify']['post']['responses'] ?? null;
        self::assertIsArray($verifyResponses);
        self::assertArrayHasKey(200, $verifyResponses);
    }
}
