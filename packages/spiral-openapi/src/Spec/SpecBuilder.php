<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Spec;

use Spiral\Translator\TranslatorInterface;
use GianTiaga\SpiralOpenApi\Config\OpenApiGeneratorConfig;
use GianTiaga\SpiralOpenApi\Exception\OpenApiGenerationException;
use GianTiaga\SpiralOpenApi\Logging\DebugLogger;
use GianTiaga\SpiralOpenApi\Model\ClassMetadata;
use GianTiaga\SpiralOpenApi\Model\MethodMetadata;
use GianTiaga\SpiralOpenApi\Model\PropertyMetadata;
use GianTiaga\SpiralOpenApi\Schema\NullableSchema;
use GianTiaga\SpiralOpenApi\Schema\SchemaBuilder;
use GianTiaga\SpiralOpenApi\Schema\SchemaRegistry;

final readonly class SpecBuilder
{
    public function __construct(private DebugLogger $logger, private TranslatorInterface $translator) {}
    /**
     * @param list<ClassMetadata> $classes
     */
    public function build(array $classes, OpenApiGeneratorConfig $config): OpenApiBuildResult
    {
        $classesByName = $this->classesByName($classes);
        $classesByShortName = $this->classesByShortName($classes);
        $schemaRegistry = new SchemaRegistry();
        $nullableSchema = new NullableSchema(openApiVersion: $config->openApiVersion);
        $schemaBuilder = new SchemaBuilder(classesByName: $classesByName, classesByShortName: $classesByShortName, schemaRegistry: $schemaRegistry, nullableSchema: $nullableSchema);
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
                $operationId = $this->operationId(classMetadata: $classMetadata, methodMetadata: $methodMetadata);
                if (isset($operationIds[$operationId])) {
                    throw new OpenApiGenerationException(\sprintf('Найден повторяющийся operationId: %s.', $operationId));
                }
                $operationIds[$operationId] = true;
                foreach ($methodMetadata->route->methods as $httpMethod) {
                    $paths[$this->pathWithoutRoutePrefix(routePath: $methodMetadata->route->path, routePrefix: $config->routePrefix)][\strtolower($httpMethod)] = $this->operation(classMetadata: $classMetadata, methodMetadata: $methodMetadata, operationId: $operationId, schemaBuilder: $schemaBuilder, classesByName: $classesByName, config: $config, nullableSchema: $nullableSchema);
                    $operationCount++;
                }
            }
        }
        $this->ensureErrorResponseSchema(schemaRegistry: $schemaRegistry, config: $config, nullableSchema: $nullableSchema);
        return new OpenApiBuildResult(
            spec: ['openapi' => $config->openApiVersion, 'info' => ['title' => $config->title, 'version' => $config->version], 'servers' => [['url' => $config->routePrefix]], 'paths' => $paths, 'components' => ['schemas' => $schemaRegistry->all()]],
            operationCount: $operationCount,
            schemaCount: $schemaRegistry->count(),
        );
    }
    private function pathWithoutRoutePrefix(string $routePath, string $routePrefix): string
    {
        $normalizedRoutePath = $this->pathWithLeadingSlash(path: $routePath);
        $normalizedRoutePrefix = \rtrim(string: $this->pathWithLeadingSlash(path: $routePrefix), characters: '/');
        if ($normalizedRoutePrefix === '') {
            return $normalizedRoutePath;
        }
        if ($normalizedRoutePath === $normalizedRoutePrefix) {
            return '/';
        }
        if (!\str_starts_with(haystack: $normalizedRoutePath, needle: \sprintf('%s/', $normalizedRoutePrefix))) {
            return $normalizedRoutePath;
        }
        return $this->pathWithLeadingSlash(path: \substr(string: $normalizedRoutePath, offset: \strlen($normalizedRoutePrefix)));
    }
    private function pathWithLeadingSlash(string $path): string
    {
        $trimmedPath = \trim(string: $path, characters: '/');
        if ($trimmedPath === '') {
            return '/';
        }
        return \sprintf('/%s', $trimmedPath);
    }
    /**
     * @param array<string, ClassMetadata> $classesByName
     * @return array<string, mixed>
     */
    private function operation(ClassMetadata $classMetadata, MethodMetadata $methodMetadata, string $operationId, SchemaBuilder $schemaBuilder, array $classesByName, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): array
    {
        $successResponses = $this->successResponses(classMetadata: $classMetadata, methodMetadata: $methodMetadata, schemaBuilder: $schemaBuilder, config: $config, nullableSchema: $nullableSchema);
        $errorResponse = ['description' => $this->translator->trans(id: 'gian_tiaga.spiral_openapi.api_error'), 'content' => ['application/json' => ['schema' => $this->errorResponseSchema(config: $config)]]];
        // Union (+) сохраняет числовой ключ кода ответа (200/204); spread [...$successResponses] переиндексировал бы его в 0.
        $operation = ['operationId' => $operationId, 'description' => $methodMetadata->openApi?->description ?: $methodMetadata->summary, 'parameters' => $this->parameters(methodMetadata: $methodMetadata, schemaBuilder: $schemaBuilder, classesByName: $classesByName), 'responses' => $successResponses + ['default' => $errorResponse]];
        $requestBody = $this->requestBody(methodMetadata: $methodMetadata, schemaBuilder: $schemaBuilder, classesByName: $classesByName);
        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }
        return $operation;
    }
    /**
     * @return array<int, mixed>
     */
    private function successResponses(ClassMetadata $classMetadata, MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): array
    {
        if ($methodMetadata->returnType !== null && $methodMetadata->returnType === $config->responseWrapperMapping->emptyResponseClass) {
            $this->logger->debug(\sprintf('Операция %s::%s отдаёт 204 No Content (EmptySuccessResponse).', $classMetadata->className, $methodMetadata->name));
            return ['204' => ['description' => $this->translator->trans(id: 'gian_tiaga.spiral_openapi.successful_response')]];
        }
        return ['200' => $this->successResponse(classMetadata: $classMetadata, methodMetadata: $methodMetadata, schemaBuilder: $schemaBuilder, config: $config, nullableSchema: $nullableSchema)];
    }
    /**
     * @return array<string, mixed>
     */
    private function successResponse(ClassMetadata $classMetadata, MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): array
    {
        if ($methodMetadata->fileResponse !== null) {
            return ['description' => $this->translator->trans(id: 'gian_tiaga.spiral_openapi.successful_response'), 'content' => [$this->mediaType(contentType: $methodMetadata->fileResponse->contentType) => ['schema' => $methodMetadata->fileResponse->binary ? ['type' => 'string', 'format' => 'binary'] : ['type' => 'string']]]];
        }
        $genericReturnType = $methodMetadata->genericReturnType;
        if ($genericReturnType === null) {
            throw new OpenApiGenerationException(\sprintf('Для метода %s::%s отсутствует PHPDoc @return с generic-типом ответа.', $classMetadata->className, $methodMetadata->name));
        }
        return ['description' => $this->translator->trans(id: 'gian_tiaga.spiral_openapi.successful_response'), 'content' => ['application/json' => ['schema' => $this->responseSchema(wrapperClass: $genericReturnType->wrapperClass, resourceClass: $genericReturnType->resourceClass, schemaBuilder: $schemaBuilder, config: $config, nullableSchema: $nullableSchema)]]];
    }
    private function mediaType(string $contentType): string
    {
        $mediaType = \strstr(haystack: $contentType, needle: ';', before_needle: true);
        return $mediaType === false ? $contentType : $mediaType;
    }
    private function ensureErrorResponseSchema(SchemaRegistry $schemaRegistry, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): void
    {
        $schemaName = $this->shortName(className: $config->responseWrapperMapping->errorResponseClass);
        if ($schemaRegistry->has($schemaName)) {
            return;
        }
        $schemaRegistry->add(schemaName: $schemaName, schema: ['type' => 'object', 'properties' => ['message' => ['type' => 'string'], 'code' => $nullableSchema->makeNullable(['type' => 'integer'])], 'required' => ['message']]);
    }
    /**
     * @return array<string, string>
     */
    private function errorResponseSchema(OpenApiGeneratorConfig $config): array
    {
        return ['$ref' => \sprintf('#/components/schemas/%s', $this->shortName(className: $config->responseWrapperMapping->errorResponseClass))];
    }
    /**
     * @param array<string, ClassMetadata> $classesByName
     * @return list<mixed>
     */
    private function parameters(MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, array $classesByName): array
    {
        $parameters = [];
        foreach ($methodMetadata->parameters as $parameterMetadata) {
            if (!$this->pathContainsParameter(methodMetadata: $methodMetadata, parameterName: $parameterMetadata->name)) {
                continue;
            }
            $parameters[] = ['name' => $parameterMetadata->name, 'in' => 'path', 'required' => true, 'schema' => $schemaBuilder->schemaForType($parameterMetadata->type)];
        }
        foreach ($this->filterProperties(methodMetadata: $methodMetadata, source: PropertyMetadata::SOURCE_QUERY, classesByName: $classesByName) as $propertyMetadata) {
            $parameters[] = ['name' => $propertyMetadata->name, 'in' => 'query', 'required' => $propertyMetadata->isRequired(), 'schema' => $schemaBuilder->schemaForProperty($propertyMetadata)];
        }
        return $parameters;
    }
    /**
     * @param array<string, ClassMetadata> $classesByName
     * @return null|array<string, mixed>
     */
    private function requestBody(MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, array $classesByName): array|null
    {
        $bodyProperties = [...$this->filterProperties(methodMetadata: $methodMetadata, source: PropertyMetadata::SOURCE_BODY, classesByName: $classesByName), ...$this->filterProperties(methodMetadata: $methodMetadata, source: PropertyMetadata::SOURCE_DATA, classesByName: $classesByName)];
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
        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        return ['required' => true, 'content' => ['application/json' => ['schema' => $schema]]];
    }
    /**
     * @return array<string, mixed>
     */
    private function responseSchema(string $wrapperClass, string $resourceClass, SchemaBuilder $schemaBuilder, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): array
    {
        $wrapperShortName = $this->shortName($wrapperClass);
        if ($this->shortName($config->responseWrapperMapping->dataResponseClass) === $wrapperShortName) {
            return ['type' => 'object', 'properties' => ['data' => $schemaBuilder->referenceFor($resourceClass)], 'required' => ['data']];
        }
        if ($this->shortName($config->responseWrapperMapping->collectionResponseClass) === $wrapperShortName) {
            return ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $schemaBuilder->referenceFor($resourceClass)]], 'required' => ['data']];
        }
        if ($this->shortName($config->responseWrapperMapping->paginationResponseClass) === $wrapperShortName) {
            return ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $schemaBuilder->referenceFor($resourceClass)], 'meta' => ['type' => 'object', 'properties' => ['nextCursor' => $nullableSchema->makeNullable(['type' => 'string']), 'limit' => ['type' => 'integer']], 'required' => ['nextCursor', 'limit']]], 'required' => ['data', 'meta']];
        }
        throw new OpenApiGenerationException(\sprintf('Неизвестная обёртка ответа: %s.', $wrapperClass));
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
        return $methodMetadata->route !== null && (\str_contains(haystack: $methodMetadata->route->path, needle: \sprintf('<%s>', $parameterName)) || \str_contains(haystack: $methodMetadata->route->path, needle: \sprintf('{%s}', $parameterName)));
    }
    private function operationId(ClassMetadata $classMetadata, MethodMetadata $methodMetadata): string
    {
        if ($methodMetadata->openApi !== null && $methodMetadata->openApi->id !== '') {
            return $methodMetadata->openApi->id;
        }
        if ($methodMetadata->route?->name !== null) {
            return \str_replace(search: '.', replace: '_', subject: $methodMetadata->route->name);
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
        $parts = \explode(separator: '\\', string: \trim(string: $className, characters: '\\'));
        return (string) \end($parts);
    }
}
