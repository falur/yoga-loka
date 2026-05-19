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
        public ?string $returnType,
        public ?GenericReturnType $genericReturnType,
        public ?FileResponseMetadata $fileResponse,
        public ?RouteMetadata $route,
        public ?OpenApiMetadata $openApi,
        public array $parameters,
    ) {}
}
