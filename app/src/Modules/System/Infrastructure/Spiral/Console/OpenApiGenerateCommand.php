<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Console;

use App\Shared\Infrastructure\Spiral\Configuration\OpenApi\OpenApiConfig;
use App\Shared\Infrastructure\Spiral\DirectoryAlias;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use GianTiaga\SpiralOpenApi\Exception\OpenApiException;
use GianTiaga\SpiralOpenApi\OpenApiGenerator;

#[AsCommand(
    name: 'openapi:generate',
    description: 'Сгенерировать OpenAPI YAML из типизированного HTTP-слоя',
)]
final class OpenApiGenerateCommand extends Command
{
    public function perform(
        OpenApiConfig $openApiConfig,
        DirectoriesInterface $directories,
        OpenApiGenerator $openApiGenerator,
    ): int {
        if (!$openApiConfig->enabled) {
            $this->warning('Генерация OpenAPI выключена в конфигурации.');

            return SymfonyCommand::SUCCESS;
        }

        try {
            $openApiGenerationResult = $openApiGenerator->generate(
                config: $openApiConfig->toGeneratorConfig(projectRoot: $directories->get(name: DirectoryAlias::Root->value)),
                debugWriter: fn(string $message): null => $this->writeDebug(message: $message),
            );
        } catch (OpenApiException $exception) {
            $this->error('Ошибка генерации OpenAPI: ' . $exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $this->info(\strtr(
            string: 'OpenAPI YAML записан: {file}. Операций: {operations}, schemas: {schemas}.',
            from: [
                '{file}' => $openApiGenerationResult->outputFile,
                '{operations}' => (string) $openApiGenerationResult->operationCount,
                '{schemas}' => (string) $openApiGenerationResult->schemaCount,
            ],
        ));

        return SymfonyCommand::SUCCESS;
    }

    private function writeDebug(string $message): null
    {
        $this->comment('[openapi] ' . $message);

        return null;
    }
}
