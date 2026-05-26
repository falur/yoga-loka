<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Writer;

use Symfony\Component\Yaml\Yaml;
use GianTiaga\SpiralOpenApi\Exception\OpenApiGenerationException;
final readonly class YamlWriter
{
    /**
     * @param array<string, mixed> $spec
     */
    public function write(array $spec, string $outputFile): void
    {
        $directory = \dirname($outputFile);
        if (!\is_dir($directory) && !\mkdir(directory: $directory, permissions: 0775, recursive: true) && !\is_dir($directory)) {
            throw new OpenApiGenerationException(\sprintf('Не удалось создать каталог OpenAPI YAML: %s.', $directory));
        }
        $temporaryFile = \sprintf('%s.tmp', $outputFile);
        $yaml = Yaml::dump(input: $spec, inline: 6, indent: 2, flags: Yaml::DUMP_OBJECT_AS_MAP);
        if (\file_put_contents(filename: $temporaryFile, data: $yaml) === false) {
            throw new OpenApiGenerationException(\sprintf('Не удалось записать временный OpenAPI YAML: %s.', $temporaryFile));
        }
        if (!\rename(from: $temporaryFile, to: $outputFile)) {
            throw new OpenApiGenerationException(\sprintf('Не удалось заменить OpenAPI YAML: %s.', $outputFile));
        }
    }
}
