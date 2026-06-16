<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\OpenApi;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use GianTiaga\SpiralOpenApi\Config\OpenApiGeneratorConfig;
use GianTiaga\SpiralOpenApi\Config\ResponseWrapperMapping;
use GianTiaga\SpiralOpenApi\Response\CollectionResponse;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;

final readonly class OpenApiConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'openapi';
    }

    public function __construct(
        public bool $enabled,
        public bool $swaggerEnabled,
        public string $sourcePath,
        public string $apiNamespace,
        public string $routePrefix,
        public string $outputFile,
        public string $title,
        public string $version,
        public bool $debug,
    ) {}

    public function sourcePath(string $projectRoot): string
    {
        return \sprintf(
            '%s/%s',
            \rtrim(string: $projectRoot, characters: \DIRECTORY_SEPARATOR),
            \ltrim(string: $this->sourcePath, characters: \DIRECTORY_SEPARATOR),
        );
    }

    public function outputFile(string $projectRoot): string
    {
        return \sprintf(
            '%s/%s',
            \rtrim(string: $projectRoot, characters: \DIRECTORY_SEPARATOR),
            \ltrim(string: $this->outputFile, characters: \DIRECTORY_SEPARATOR),
        );
    }

    public function toGeneratorConfig(string $projectRoot): OpenApiGeneratorConfig
    {
        return new OpenApiGeneratorConfig(
            projectRoot: $projectRoot,
            sourcePaths: [$this->sourcePath(projectRoot: $projectRoot)],
            apiNamespace: $this->apiNamespace,
            routePrefix: $this->routePrefix,
            outputFile: $this->outputFile(projectRoot: $projectRoot),
            title: $this->title,
            version: $this->version,
            responseWrapperMapping: new ResponseWrapperMapping(
                dataResponseClass: DataResponse::class,
                collectionResponseClass: CollectionResponse::class,
                paginationResponseClass: PaginationResponse::class,
                errorResponseClass: ErrorResponse::class,
                emptyResponseClass: EmptySuccessResponse::class,
            ),
            debug: $this->debug,
        );
    }
}
