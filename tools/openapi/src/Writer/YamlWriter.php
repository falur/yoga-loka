<?php

declare(strict_types=1);

namespace Tools\OpenApi\Writer;

use Symfony\Component\Yaml\Yaml;
use Tools\OpenApi\Exception\OpenApiGenerationException;

final readonly class YamlWriter
{
    /**
     * @param array<string, mixed> $spec
     */
    public function write(array $spec, string $outputFile): void
    {
        $directory = \dirname($outputFile);

        if (!\is_dir($directory) && !\mkdir($directory, 0o775, true) && !\is_dir($directory)) {
            throw new OpenApiGenerationException(\sprintf('Не удалось создать каталог OpenAPI YAML: %s.', $directory));
        }

        $temporaryFile = \sprintf('%s.tmp', $outputFile);
        $yaml = Yaml::dump($spec, inline: 6, indent: 2, flags: Yaml::DUMP_OBJECT_AS_MAP);

        if (\file_put_contents($temporaryFile, $yaml) === false) {
            throw new OpenApiGenerationException(\sprintf('Не удалось записать временный OpenAPI YAML: %s.', $temporaryFile));
        }

        if (!\rename($temporaryFile, $outputFile)) {
            throw new OpenApiGenerationException(\sprintf('Не удалось заменить OpenAPI YAML: %s.', $outputFile));
        }
    }
}
