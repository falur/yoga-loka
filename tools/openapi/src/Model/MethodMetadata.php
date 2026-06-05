<?php

declare(strict_types=1);

namespace Tools\OpenApi\Model;

final readonly class MethodMetadata
{
    /**
     * @param list<ParameterMetadata> $parameters
     */
    public function __construct(
        public string $name,
        public string $summary,
        public string|null $returnType,
        public GenericReturnType|null $genericReturnType,
        public FileResponseMetadata|null $fileResponse,
        public RouteMetadata|null $route,
        public OpenApiMetadata|null $openApi,
        public array $parameters,
    ) {}
}
