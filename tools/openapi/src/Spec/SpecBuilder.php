<?php

declare(strict_types=1);

namespace Tools\OpenApi\Spec;

use Spiral\Translator\TranslatorInterface;
use Tools\OpenApi\Config\OpenApiGeneratorConfig;
use Tools\OpenApi\Exception\OpenApiGenerationException;
use Tools\OpenApi\Logging\DebugLogger;
use Tools\OpenApi\Model\ClassMetadata;
use Tools\OpenApi\Model\MethodMetadata;
use Tools\OpenApi\Model\PropertyMetadata;
use Tools\OpenApi\Schema\SchemaBuilder;
use Tools\OpenApi\Schema\SchemaRegistry;

final readonly class SpecBuilder
{
    public function __construct(
        private DebugLogger $logger,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param list<ClassMetadata> $classes
     * @return array{0: array<string, mixed>, 1: int, 2: int}
     */
    public function build(array $classes, OpenApiGeneratorConfig $config): array
    {
        $classesByName = $this->classesByName($classes);
        $classesByShortName = $this->classesByShortName($classes);
        $schemaRegistry = new SchemaRegistry();
        $schemaBuilder = new SchemaBuilder($classesByName, $classesByShortName, $schemaRegistry);

        $paths = [];
        $operationIds = [];
        $operationCount = 0;

        foreach ($classes as $classMetadata) {
            foreach ($classMetadata->methods as $methodMetadata) {
                if ($methodMetadata->route === null) {
                    continue;
                }

                if ($methodMetadata->openApi?->ignore === true) {
                    continue;
                }

                $this->logger->debug(\sprintf('Найдена OpenAPI-операция: %s::%s.', $classMetadata->className, $methodMetadata->name));
                $operationId = $this->operationId($classMetadata, $methodMetadata);

                if (isset($operationIds[$operationId])) {
                    throw new OpenApiGenerationException(\sprintf('Найден повторяющийся operationId: %s.', $operationId));
                }

                $operationIds[$operationId] = true;

                foreach ($methodMetadata->route->methods as $httpMethod) {
                    $paths[$this->pathWithoutRoutePrefix($methodMetadata->route->path, $config->routePrefix)][\strtolower($httpMethod)] = $this->operation(
                        classMetadata: $classMetadata,
                        methodMetadata: $methodMetadata,
                        operationId: $operationId,
                        schemaBuilder: $schemaBuilder,
                        classesByName: $classesByName,
                        config: $config,
                    );
                    $operationCount++;
                }
            }
        }

        $this->ensureErrorResponseSchema(schemaRegistry: $schemaRegistry, config: $config);

        return [[
            'openapi' => $config->openApiVersion,
            'info' => [
                'title' => $config->title,
                'version' => $config->version,
            ],
            'servers' => [
                ['url' => $config->routePrefix],
            ],
            'paths' => $paths,
            'components' => [
                'schemas' => $schemaRegistry->all(),
            ],
        ], $operationCount, $schemaRegistry->count()];
    }

    private function pathWithoutRoutePrefix(string $routePath, string $routePrefix): string
    {
        $normalizedRoutePath = $this->pathWithLeadingSlash($routePath);
        $normalizedRoutePrefix = \rtrim($this->pathWithLeadingSlash($routePrefix), '/');

        if ($normalizedRoutePrefix === '') {
            return $normalizedRoutePath;
        }

        if ($normalizedRoutePath === $normalizedRoutePrefix) {
            return '/';
        }

        if (!\str_starts_with($normalizedRoutePath, \sprintf('%s/', $normalizedRoutePrefix))) {
            return $normalizedRoutePath;
        }

        return $this->pathWithLeadingSlash(\substr($normalizedRoutePath, \strlen($normalizedRoutePrefix)));
    }

    private function pathWithLeadingSlash(string $path): string
    {
        $trimmedPath = \trim($path, '/');

        if ($trimmedPath === '') {
            return '/';
        }

        return \sprintf('/%s', $trimmedPath);
    }

    /**
     * @param array<string, ClassMetadata> $classesByName
     * @return array<string, mixed>
     */
    private function operation(
        ClassMetadata $classMetadata,
        MethodMetadata $methodMetadata,
        string $operationId,
        SchemaBuilder $schemaBuilder,
        array $classesByName,
        OpenApiGeneratorConfig $config,
    ): array {
        $operation = [
            'operationId' => $operationId,
            'description' => $methodMetadata->openApi?->description ?: $methodMetadata->summary,
            'parameters' => $this->parameters($methodMetadata, $schemaBuilder, $classesByName),
            'responses' => [
                '200' => $this->successResponse($classMetadata, $methodMetadata, $schemaBuilder, $config),
                'default' => [
                    'description' => $this->translator->trans(id: 'yoga_loka.openapi.api_error'),
                    'content' => [
                        'application/json' => [
                            'schema' => $this->errorResponseSchema(config: $config),
                        ],
                    ],
                ],
            ],
        ];

        $requestBody = $this->requestBody($methodMetadata, $schemaBuilder, $classesByName);

        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }

        return $operation;
    }

    /**
     * @return array<string, mixed>
     */
    private function successResponse(
        ClassMetadata $classMetadata,
        MethodMetadata $methodMetadata,
        SchemaBuilder $schemaBuilder,
        OpenApiGeneratorConfig $config,
    ): array {
        if ($methodMetadata->fileResponse !== null) {
            return [
                'description' => $this->translator->trans(id: 'yoga_loka.openapi.successful_response'),
                'content' => [
                    $this->mediaType($methodMetadata->fileResponse->contentType) => [
                        'schema' => $methodMetadata->fileResponse->binary
                            ? ['type' => 'string', 'format' => 'binary']
                            : ['type' => 'string'],
                    ],
                ],
            ];
        }

        $genericReturnType = $methodMetadata->genericReturnType;

        if ($genericReturnType === null) {
            throw new OpenApiGenerationException(
                \sprintf('Для метода %s::%s отсутствует PHPDoc @return с response generic.', $classMetadata->className, $methodMetadata->name),
            );
        }

        return [
            'description' => $this->translator->trans(id: 'yoga_loka.openapi.successful_response'),
            'content' => [
                'application/json' => [
                    'schema' => $this->responseSchema($genericReturnType->wrapperClass, $genericReturnType->resourceClass, $schemaBuilder, $config),
                ],
            ],
        ];
    }

    private function mediaType(string $contentType): string
    {
        $mediaType = \strstr($contentType, ';', true);

        return $mediaType === false ? $contentType : $mediaType;
    }

    private function ensureErrorResponseSchema(SchemaRegistry $schemaRegistry, OpenApiGeneratorConfig $config): void
    {
        $schemaName = $this->shortName($config->responseWrapperMapping->errorResponseClass);

        if ($schemaRegistry->has($schemaName)) {
            return;
        }

        $schemaRegistry->add($schemaName, [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
                'code' => ['type' => 'integer', 'nullable' => true],
            ],
            'required' => ['message'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function errorResponseSchema(OpenApiGeneratorConfig $config): array
    {
        return ['$ref' => \sprintf('#/components/schemas/%s', $this->shortName($config->responseWrapperMapping->errorResponseClass))];
    }

    /**
     * @param array<string, ClassMetadata> $classesByName
     * @return list<array<string, mixed>>
     */
    private function parameters(MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, array $classesByName): array
    {
        $parameters = [];

        foreach ($methodMetadata->parameters as $parameterMetadata) {
            if (!$this->pathContainsParameter($methodMetadata, $parameterMetadata->name)) {
                continue;
            }

            $parameters[] = [
                'name' => $parameterMetadata->name,
                'in' => 'path',
                'required' => true,
                'schema' => $schemaBuilder->schemaForType($parameterMetadata->type),
            ];
        }

        foreach ($this->filterProperties($methodMetadata, PropertyMetadata::SOURCE_QUERY, $classesByName) as $propertyMetadata) {
            $parameters[] = [
                'name' => $propertyMetadata->name,
                'in' => 'query',
                'required' => $propertyMetadata->isRequired(),
                'schema' => $schemaBuilder->schemaForProperty($propertyMetadata),
            ];
        }

        return $parameters;
    }

    /**
     * @param array<string, ClassMetadata> $classesByName
     * @return null|array<string, mixed>
     */
    private function requestBody(MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, array $classesByName): array|null
    {
        $bodyProperties = [
            ...$this->filterProperties($methodMetadata, PropertyMetadata::SOURCE_BODY, $classesByName),
            ...$this->filterProperties($methodMetadata, PropertyMetadata::SOURCE_DATA, $classesByName),
        ];

        if ($bodyProperties === []) {
            return null;
        }

        $properties = [];
        $required = [];

        foreach ($bodyProperties as $propertyMetadata) {
            $properties[$propertyMetadata->name] = $schemaBuilder->schemaForProperty($propertyMetadata);

            if ($propertyMetadata->isRequired()) {
                $required[] = $propertyMetadata->name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(
        string $wrapperClass,
        string $resourceClass,
        SchemaBuilder $schemaBuilder,
        OpenApiGeneratorConfig $config,
    ): array {
        $wrapperShortName = $this->shortName($wrapperClass);

        if ($this->shortName($config->responseWrapperMapping->dataResponseClass) === $wrapperShortName) {
            return [
                'type' => 'object',
                'properties' => [
                    'data' => $schemaBuilder->referenceFor($resourceClass),
                ],
                'required' => ['data'],
            ];
        }

        if ($this->shortName($config->responseWrapperMapping->collectionResponseClass) === $wrapperShortName) {
            return [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => $schemaBuilder->referenceFor($resourceClass),
                    ],
                ],
                'required' => ['data'],
            ];
        }

        if ($this->shortName($config->responseWrapperMapping->paginationResponseClass) === $wrapperShortName) {
            return [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => $schemaBuilder->referenceFor($resourceClass),
                    ],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'nextCursor' => ['type' => 'string', 'nullable' => true],
                            'limit' => ['type' => 'integer'],
                        ],
                        'required' => ['nextCursor', 'limit'],
                    ],
                ],
                'required' => ['data', 'meta'],
            ];
        }

        throw new OpenApiGenerationException(\sprintf('Неизвестный generic response wrapper: %s.', $wrapperClass));
    }

    /**
     * @param array<string, ClassMetadata> $classesByName
     * @return list<PropertyMetadata>
     */
    private function filterProperties(MethodMetadata $methodMetadata, string $source, array $classesByName): array
    {
        $properties = [];

        foreach ($methodMetadata->parameters as $parameterMetadata) {
            $filterClass = $parameterMetadata->type;

            if (!isset($classesByName[$filterClass])) {
                continue;
            }

            foreach ($classesByName[$filterClass]->properties as $propertyMetadata) {
                if ($propertyMetadata->source === $source) {
                    $properties[] = $propertyMetadata;
                }
            }
        }

        return $properties;
    }

    private function pathContainsParameter(MethodMetadata $methodMetadata, string $parameterName): bool
    {
        return $methodMetadata->route !== null
            && (\str_contains($methodMetadata->route->path, \sprintf('<%s>', $parameterName))
                || \str_contains($methodMetadata->route->path, \sprintf('{%s}', $parameterName)));
    }

    private function operationId(ClassMetadata $classMetadata, MethodMetadata $methodMetadata): string
    {
        if ($methodMetadata->openApi !== null && $methodMetadata->openApi->id !== '') {
            return $methodMetadata->openApi->id;
        }

        if ($methodMetadata->route?->name !== null) {
            return \str_replace('.', '_', $methodMetadata->route->name);
        }

        return \sprintf('%s_%s', $classMetadata->shortName, $methodMetadata->name);
    }

    /**
     * @param list<ClassMetadata> $classes
     * @return array<string, ClassMetadata>
     */
    private function classesByName(array $classes): array
    {
        $classesByName = [];

        foreach ($classes as $classMetadata) {
            $classesByName[$classMetadata->className] = $classMetadata;
        }

        return $classesByName;
    }

    /**
     * @param list<ClassMetadata> $classes
     * @return array<string, ClassMetadata>
     */
    private function classesByShortName(array $classes): array
    {
        $classesByShortName = [];

        foreach ($classes as $classMetadata) {
            $classesByShortName[$classMetadata->shortName] = $classMetadata;
        }

        return $classesByShortName;
    }

    private function shortName(string $className): string
    {
        $parts = \explode('\\', \trim($className, '\\'));

        return (string) \end($parts);
    }
}
