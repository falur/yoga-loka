<?php

declare(strict_types=1);

namespace Tools\OpenApi;

use Spiral\Translator\TranslatorInterface;
use Tools\OpenApi\Config\OpenApiGeneratorConfig;
use Tools\OpenApi\Logging\DebugLogger;
use Tools\OpenApi\Parser\PhpAstParser;
use Tools\OpenApi\Scanner\FileScanner;
use Tools\OpenApi\Spec\OpenApiGenerationResult;
use Tools\OpenApi\Spec\SpecBuilder;
use Tools\OpenApi\Writer\YamlWriter;

final readonly class OpenApiGenerator
{
    public function __construct(
        private TranslatorInterface $translator,
        private FileScanner $fileScanner = new FileScanner(),
        private PhpAstParser $phpAstParser = new PhpAstParser(),
        private YamlWriter $yamlWriter = new YamlWriter(),
    ) {}

    /**
     * @param null|callable(string): void $debugWriter
     */
    public function generate(OpenApiGeneratorConfig $config, mixed $debugWriter = null): OpenApiGenerationResult
    {
        $logger = new DebugLogger($config->debug, $debugWriter);

        $logger->debug('Старт генерации OpenAPI.');
        $config->validate();
        $logger->debug(\sprintf('Namespace API: %s.', $config->apiNamespace));
        $logger->debug(\sprintf('Файл результата: %s.', $config->outputFile));

        $sourceFiles = $this->fileScanner->scan($config->sourcePaths);
        $logger->debug(\sprintf('Найдено PHP-файлов для анализа: %d.', \count($sourceFiles)));

        $classes = $this->phpAstParser->parse($sourceFiles, $config->apiNamespace);
        $logger->debug(\sprintf('Найдено классов внутри API namespace: %d.', \count($classes)));

        [$spec, $operationCount, $schemaCount] = new SpecBuilder(
            logger: $logger,
            translator: $this->translator,
        )->build($classes, $config);
        $this->yamlWriter->write($spec, $config->outputFile);

        $logger->debug(\sprintf('OpenAPI YAML записан: %s.', $config->outputFile));
        $logger->debug(\sprintf('Операций: %d, schemas: %d.', $operationCount, $schemaCount));

        return new OpenApiGenerationResult(
            outputFile: $config->outputFile,
            operationCount: $operationCount,
            schemaCount: $schemaCount,
        );
    }
}
