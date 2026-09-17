<?php

declare(strict_types=1);

namespace App\Modules\System\Tests\Integration\Spiral;

use Spiral\Testing\Attribute\Config;
use Spiral\Translator\TranslatorInterface;
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
