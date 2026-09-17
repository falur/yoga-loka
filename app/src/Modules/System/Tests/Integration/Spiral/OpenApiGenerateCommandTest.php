<?php

declare(strict_types=1);

namespace App\Modules\System\Tests\Integration\Spiral;

use Spiral\Testing\Attribute\Config;
use Spiral\Translator\TranslatorInterface;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class OpenApiGenerateCommandTest extends TestCase
{
    #[Config('openapi.outputFile', 'runtime/openapi-i18n-test.yml')]
    public function testGenerateCommandUsesCurrentTranslatorLocale(): void
    {
        $this->getContainer()->get(TranslatorInterface::class)->setLocale('ru');

        $this->runCommand(command: 'openapi:generate');

        $outputFile = $this->rootDirectory() . '/runtime/openapi-i18n-test.yml';
        $contents = \file_get_contents($outputFile);

        self::assertIsString($contents);
        self::assertStringContainsString('Успешный ответ.', $contents);
        self::assertStringContainsString('Ошибка API.', $contents);
        self::assertStringContainsString('/health:', $contents);
    }

    #[Config('openapi.outputFile', 'runtime/openapi-security-test.yml')]
    public function testGenerateCommandDeclaresBearerSecurityByRouteAccessAttribute(): void
    {
        $this->runCommand(command: 'openapi:generate');

        $spec = Yaml::parseFile($this->rootDirectory() . '/runtime/openapi-security-test.yml');

        self::assertIsArray($spec);
        self::assertSame(
            ['type' => 'http', 'scheme' => 'bearer'],
            $spec['components']['securitySchemes']['bearerAuth'] ?? null,
        );
        // Маршрут с #[AuthenticatedRoute] несёт требование bearer-схемы.
        self::assertSame(
            [['bearerAuth' => []]],
            $spec['paths']['/auth/logout']['post']['security'] ?? null,
        );
        // Маршрут с #[PublicRoute] объявляет доступ без требований — пустой список security,
        // а не отсутствие ключа: так операция явно отказывается от схемы безопасности.
        self::assertSame([], $spec['paths']['/health']['get']['security'] ?? null);
        self::assertSame([], $spec['paths']['/auth/code/request']['post']['security'] ?? null);
    }

    #[Config('openapi.enabled', false)]
    public function testGenerateCommandWarnsWhenDisabled(): void
    {
        $output = $this->runCommand(command: 'openapi:generate');

        self::assertStringContainsString('Генерация OpenAPI выключена в конфигурации.', $output);
    }

    #[Config('openapi.sourcePaths', ['definitely-missing-openapi-source'])]
    public function testGenerateCommandReportsGeneratorError(): void
    {
        $output = $this->runCommand(command: 'openapi:generate');

        self::assertStringContainsString('Ошибка генерации OpenAPI', $output);
    }
}
