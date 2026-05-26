<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Config;

use GianTiaga\SpiralOpenApi\Exception\OpenApiConfigurationException;
final readonly class OpenApiGeneratorConfig
{
    /**
     * @param list<string> $sourcePaths
     */
    public function __construct(public string $projectRoot, public array $sourcePaths, public string $apiNamespace, public string $routePrefix, public string $outputFile, public string $title, public string $version, public ResponseWrapperMapping $responseWrapperMapping, public string $openApiVersion = '3.1.0', public bool $debug = false)
    {
    }
    public function validate(): void
    {
        if ($this->sourcePaths === []) {
            throw new OpenApiConfigurationException('Не указан ни один каталог исходного кода API.');
        }
        foreach ($this->sourcePaths as $sourcePath) {
            if (!\is_dir($sourcePath)) {
                throw new OpenApiConfigurationException(\sprintf('Каталог исходного кода API не найден: %s.', $sourcePath));
            }
        }
        if (\trim(string: $this->apiNamespace, characters: '\\') === '') {
            throw new OpenApiConfigurationException('Пространство имён API не должно быть пустым.');
        }
        if (\trim($this->outputFile) === '') {
            throw new OpenApiConfigurationException('Путь для OpenAPI YAML не должен быть пустым.');
        }
        $outputDirectory = \dirname($this->outputFile);
        if (!\is_dir($outputDirectory) && !\mkdir(directory: $outputDirectory, permissions: 0775, recursive: true) && !\is_dir($outputDirectory)) {
            throw new OpenApiConfigurationException(\sprintf('Не удалось создать каталог для OpenAPI YAML: %s.', $outputDirectory));
        }
    }
}
