<?php

declare(strict_types=1);

namespace Tests\Feature\Endpoint\Console;

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
    }
}
