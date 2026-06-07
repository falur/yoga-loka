<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi;

use Spiral\Translator\TranslatorInterface;
use GianTiaga\SpiralOpenApi\Config\OpenApiGeneratorConfig;
use GianTiaga\SpiralOpenApi\Logging\DebugLogger;
use GianTiaga\SpiralOpenApi\Parser\PhpAstParser;
use GianTiaga\SpiralOpenApi\Scanner\FileScanner;
use GianTiaga\SpiralOpenApi\Spec\OpenApiGenerationResult;
use GianTiaga\SpiralOpenApi\Spec\SpecBuilder;
use GianTiaga\SpiralOpenApi\Writer\YamlWriter;

final readonly class OpenApiGenerator
{
    public function __construct(private TranslatorInterface $translator, private FileScanner $fileScanner = new FileScanner(), private PhpAstParser $phpAstParser = new PhpAstParser(), private YamlWriter $yamlWriter = new YamlWriter()) {}
    /**
     * @param null|callable(string): void $debugWriter
     */
    public function generate(OpenApiGeneratorConfig $config, mixed $debugWriter = null): OpenApiGenerationResult
    {
        $logger = new DebugLogger(enabled: $config->debug, writer: $debugWriter);
        $logger->debug('Старт генерации OpenAPI.');
        $config->validate();
        $logger->debug(\sprintf('Пространство имён API: %s.', $config->apiNamespace));
        $logger->debug(\sprintf('Файл результата: %s.', $config->outputFile));
        $sourceFiles = $this->fileScanner->scan($config->sourcePaths);
        $logger->debug(\sprintf('Найдено PHP-файлов для анализа: %d.', \count($sourceFiles)));
        $classes = $this->phpAstParser->parse(sourceFiles: $sourceFiles, apiNamespace: $config->apiNamespace);
        $logger->debug(\sprintf('Найдено классов внутри пространства имён API: %d.', \count($classes)));
        $buildResult = (new SpecBuilder(logger: $logger, translator: $this->translator))->build(classes: $classes, config: $config);
        $this->yamlWriter->write(spec: $buildResult->spec, outputFile: $config->outputFile);
        $logger->debug(\sprintf('OpenAPI YAML записан: %s.', $config->outputFile));
        $logger->debug(\sprintf('Операций: %d, schemas: %d.', $buildResult->operationCount, $buildResult->schemaCount));
        return new OpenApiGenerationResult(outputFile: $config->outputFile, operationCount: $buildResult->operationCount, schemaCount: $buildResult->schemaCount);
    }
}
