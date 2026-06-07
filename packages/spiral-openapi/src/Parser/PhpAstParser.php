<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Parser;

use PhpParser\Node;
use PhpParser\Error;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use GianTiaga\SpiralOpenApi\Exception\OpenApiGenerationException;
use GianTiaga\SpiralOpenApi\Model\ClassMetadata;
use GianTiaga\SpiralOpenApi\Model\FileResponseMetadata;
use GianTiaga\SpiralOpenApi\Model\MethodMetadata;
use GianTiaga\SpiralOpenApi\Model\OpenApiMetadata;
use GianTiaga\SpiralOpenApi\Model\ParameterMetadata;
use GianTiaga\SpiralOpenApi\Model\PropertyMetadata;
use GianTiaga\SpiralOpenApi\Model\RouteMetadata;
use GianTiaga\SpiralOpenApi\Model\SourceFile;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;

final readonly class PhpAstParser
{
    public function __construct(private PhpDocReturnParser $phpDocReturnParser = new PhpDocReturnParser()) {}
    /**
     * @param list<SourceFile> $sourceFiles
     * @return list<ClassMetadata>
     */
    public function parse(array $sourceFiles, string $apiNamespace): array
    {
        $classes = [];
        foreach ($sourceFiles as $sourceFile) {
            foreach ($this->parseFile(sourceFile: $sourceFile, apiNamespace: $apiNamespace) as $classMetadata) {
                $classes[] = $classMetadata;
            }
        }
        return $classes;
    }
    /**
     * @return list<ClassMetadata>
     */
    private function parseFile(SourceFile $sourceFile, string $apiNamespace): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $statements = $parser->parse((string) \file_get_contents($sourceFile->path));
        } catch (Error) {
            throw new OpenApiGenerationException(\sprintf('Не удалось разобрать PHP-файл: %s.', $sourceFile->path));
        }
        if ($statements === null) {
            throw new OpenApiGenerationException(\sprintf('Не удалось разобрать PHP-файл: %s.', $sourceFile->path));
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $statements = $traverser->traverse($statements);
        return $this->parseStatements(statements: \array_values(array: $statements), sourceFile: $sourceFile, apiNamespace: \trim(string: $apiNamespace, characters: '\\'));
    }
    /**
     * @param list<Node> $statements
     * @return list<ClassMetadata>
     */
    private function parseStatements(array $statements, SourceFile $sourceFile, string $apiNamespace): array
    {
        $classes = [];
        foreach ($statements as $statement) {
            if (!$statement instanceof Stmt\Namespace_) {
                continue;
            }
            $namespace = $statement->name?->toString() ?? '';
            $aliases = $this->collectAliases(statements: \array_values(array: $statement->stmts));
            foreach ($statement->stmts as $namespaceStatement) {
                if ($namespaceStatement instanceof Stmt\Class_) {
                    $classMetadata = $this->parseClass(class: $namespaceStatement, sourceFile: $sourceFile, namespace: $namespace, aliases: $aliases);
                    if ($this->belongsToNamespace(className: $classMetadata->className, apiNamespace: $apiNamespace)) {
                        $classes[] = $classMetadata;
                    }
                }
                if ($namespaceStatement instanceof Stmt\Enum_) {
                    $classMetadata = $this->parseEnum(enum: $namespaceStatement, sourceFile: $sourceFile, namespace: $namespace);
                    if ($this->belongsToNamespace(className: $classMetadata->className, apiNamespace: $apiNamespace)) {
                        $classes[] = $classMetadata;
                    }
                }
            }
        }
        return $classes;
    }
    /**
     * @param list<Stmt> $statements
     * @return array<string, string>
     */
    private function collectAliases(array $statements): array
    {
        $aliases = [];
        foreach ($statements as $statement) {
            if (!$statement instanceof Stmt\Use_) {
                continue;
            }
            foreach ($statement->uses as $use) {
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $aliases[$alias] = $use->name->toString();
            }
        }
        return $aliases;
    }
    /**
     * @param array<string, string> $aliases
     */
    private function parseClass(Stmt\Class_ $class, SourceFile $sourceFile, string $namespace, array $aliases): ClassMetadata
    {
        $className = $this->resolvedName($class);
        return new ClassMetadata(filePath: $sourceFile->path, className: $className, shortName: $class->name?->toString() ?? $className, parentClass: $class->extends instanceof Name ? $this->resolvedName($class->extends) : null, enum: false, methods: $this->parseMethods(class: $class, aliases: $aliases, namespace: $namespace), properties: $this->parseProperties($class), enumCases: []);
    }
    private function parseEnum(Stmt\Enum_ $enum, SourceFile $sourceFile, string $namespace): ClassMetadata
    {
        $enumCases = [];
        foreach ($enum->stmts as $enumStatement) {
            if (!$enumStatement instanceof Stmt\EnumCase) {
                continue;
            }
            $enumCases[] = $enumStatement->expr instanceof Scalar\String_ ? $enumStatement->expr->value : $enumStatement->name->toString();
        }
        $className = $this->resolvedName($enum);
        return new ClassMetadata(filePath: $sourceFile->path, className: $className, shortName: $enum->name?->toString() ?? $className, parentClass: null, enum: true, methods: [], properties: [], enumCases: $enumCases);
    }
    /**
     * @param array<string, string> $aliases
     * @return list<MethodMetadata>
     */
    private function parseMethods(Stmt\Class_ $class, array $aliases, string $namespace): array
    {
        $methods = [];
        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic()) {
                continue;
            }
            $returnType = $method->returnType instanceof Node ? $this->typeName($method->returnType) : null;
            $methods[] = new MethodMetadata(name: $method->name->toString(), summary: $this->summaryFromDocComment(docComment: $method->getDocComment()?->getText()), returnType: $returnType, genericReturnType: $this->phpDocReturnParser->parse(docComment: $method->getDocComment()?->getText(), aliases: $aliases, namespace: $namespace), fileResponse: $this->parseFileResponse(method: $method), route: $this->parseRoute(method: $method), openApi: $this->parseOpenApi(method: $method), parameters: $this->parseParameters(method: $method));
        }
        return $methods;
    }
    private function parseFileResponse(Stmt\ClassMethod $method): FileResponseMetadata|null
    {
        foreach ((new NodeFinder())->findInstanceOf(nodes: $method->stmts ?? [], class: Expr\New_::class) as $newExpression) {
            if (!$newExpression->class instanceof Name) {
                continue;
            }
            $responseClass = $this->resolvedName($newExpression->class);
            if ($responseClass !== 'GianTiaga\SpiralOpenApi\Response\FileContentResponse' && $responseClass !== 'GianTiaga\SpiralOpenApi\Response\FileResponse') {
                continue;
            }
            return new FileResponseMetadata(responseClass: $responseClass, contentType: $this->contentTypeArgument($newExpression), binary: $responseClass === 'GianTiaga\SpiralOpenApi\Response\FileResponse');
        }
        return null;
    }
    private function contentTypeArgument(Expr\New_ $newExpression): string
    {
        foreach ($newExpression->args as $position => $argument) {
            if (!$argument instanceof Node\Arg) {
                continue;
            }
            if ($argument->name?->toString() !== 'contentType' && ($argument->name !== null || $position !== 1)) {
                continue;
            }
            if (!$argument->value instanceof Expr\ClassConstFetch || !$argument->value->class instanceof Name) {
                break;
            }
            if ($this->resolvedName($argument->value->class) !== ContentType::class) {
                break;
            }
            $caseName = $argument->value->name instanceof Node\Identifier ? $argument->value->name->toString() : '';
            foreach (ContentType::cases() as $contentType) {
                if ($contentType->name === $caseName) {
                    return $contentType->value;
                }
            }
        }
        throw new OpenApiGenerationException('Файловый ответ должен передавать contentType через enum ContentType.');
    }
    /**
     * @return list<PropertyMetadata>
     */
    private function parseProperties(Stmt\Class_ $class): array
    {
        $properties = [];
        foreach ($class->getProperties() as $property) {
            if (!$property->isPublic()) {
                continue;
            }
            foreach ($property->props as $propertyProperty) {
                $type = $property->type instanceof Node ? $this->typeName($property->type) : 'string';
                $attributeGroups = \array_values(array: $property->attrGroups);
                $properties[] = new PropertyMetadata(name: $this->inputName(attributeGroups: $attributeGroups, fallback: $propertyProperty->name->toString()), type: $this->baseTypeName($type), nullable: $property->type instanceof Node\NullableType, hasDefault: $propertyProperty->default !== null, source: $this->inputSource(attributeGroups: $attributeGroups), listItemType: $this->listItemType($property->getDocComment()?->getText()));
            }
        }
        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() !== '__construct') {
                continue;
            }
            foreach ($method->params as $parameter) {
                if (($parameter->flags & Stmt\Class_::MODIFIER_PUBLIC) !== Stmt\Class_::MODIFIER_PUBLIC) {
                    continue;
                }
                $type = $parameter->type instanceof Node ? $this->typeName($parameter->type) : 'string';
                $properties[] = new PropertyMetadata(name: $parameter->var instanceof Expr\Variable && \is_string($parameter->var->name) ? $parameter->var->name : 'value', type: $this->baseTypeName($type), nullable: $parameter->type instanceof Node\NullableType, hasDefault: $parameter->default !== null, source: PropertyMetadata::SOURCE_NONE);
            }
        }
        return $properties;
    }
    /**
     * @return list<ParameterMetadata>
     */
    private function parseParameters(Stmt\ClassMethod $method): array
    {
        $parameters = [];
        foreach ($method->params as $parameter) {
            if (!$parameter->var instanceof Expr\Variable || !\is_string($parameter->var->name)) {
                continue;
            }
            $type = $parameter->type instanceof Node ? $this->typeName($parameter->type) : 'string';
            $parameters[] = new ParameterMetadata(name: $parameter->var->name, type: $this->baseTypeName($type), nullable: $parameter->type instanceof Node\NullableType);
        }
        return $parameters;
    }
    private function parseRoute(Stmt\ClassMethod $method): RouteMetadata|null
    {
        foreach ($this->attributes($method) as $attribute) {
            if (!$this->attributeIs(attribute: $attribute, className: 'Spiral\Router\Annotation\Route')) {
                continue;
            }
            return new RouteMetadata(path: $this->stringArgument(attribute: $attribute, name: 'route', position: 0, default: ''), name: $this->nullableStringArgument(attribute: $attribute, name: 'name', position: 1), methods: $this->stringListArgument(attribute: $attribute, name: 'methods', position: 2, default: ['GET']), group: $this->nullableStringArgument(attribute: $attribute, name: 'group', position: 4), middleware: $this->stringListArgument(attribute: $attribute, name: 'middleware', position: 5, default: []), priority: $this->intArgument(attribute: $attribute, name: 'priority', position: 6, default: 0));
        }
        return null;
    }
    private function parseOpenApi(Stmt\ClassMethod $method): OpenApiMetadata|null
    {
        foreach ($this->attributes($method) as $attribute) {
            if (!$this->attributeIs(attribute: $attribute, className: 'GianTiaga\SpiralOpenApi\Attribute\OpenApi')) {
                continue;
            }
            return new OpenApiMetadata(id: $this->stringArgument(attribute: $attribute, name: 'id', position: 0, default: ''), description: $this->stringArgument(attribute: $attribute, name: 'description', position: 1, default: ''), ignore: $this->boolArgument(attribute: $attribute, name: 'ignore', position: 2, default: false));
        }
        return null;
    }
    /**
     * @param list<Node\AttributeGroup> $attributeGroups
     */
    private function inputSource(array $attributeGroups): string
    {
        foreach ($attributeGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributeName = $this->resolvedName($attribute->name);
                if (\str_ends_with(haystack: $attributeName, needle: '\Query')) {
                    return PropertyMetadata::SOURCE_QUERY;
                }
                if (\str_ends_with(haystack: $attributeName, needle: '\Route') || \str_ends_with(haystack: $attributeName, needle: '\Path')) {
                    return PropertyMetadata::SOURCE_PATH;
                }
                if (\str_ends_with(haystack: $attributeName, needle: '\Post')) {
                    return PropertyMetadata::SOURCE_BODY;
                }
                if (\str_ends_with(haystack: $attributeName, needle: '\Data') || \str_ends_with(haystack: $attributeName, needle: '\NestedFilter')) {
                    return PropertyMetadata::SOURCE_DATA;
                }
            }
        }
        return PropertyMetadata::SOURCE_NONE;
    }
    /**
     * @param list<Node\AttributeGroup> $attributeGroups
     */
    private function inputName(array $attributeGroups, string $fallback): string
    {
        foreach ($attributeGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $key = $this->nullableStringArgument(attribute: $attribute, name: 'key', position: 0);
                if ($key !== null) {
                    return $key;
                }
            }
        }
        return $fallback;
    }
    /**
     * @return list<Attribute>
     */
    private function attributes(Stmt\ClassMethod $method): array
    {
        $attributes = [];
        foreach ($method->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributes[] = $attribute;
            }
        }
        return $attributes;
    }
    private function attributeIs(Attribute $attribute, string $className): bool
    {
        $attributeName = $this->resolvedName($attribute->name);
        return $attributeName === $className || \str_ends_with(haystack: $attributeName, needle: '\\' . \basename(\str_replace(search: '\\', replace: '/', subject: $className)));
    }
    private function nullableStringArgument(Attribute $attribute, string $name, int $position): string|null
    {
        $argument = $this->argument(attribute: $attribute, name: $name, position: $position, default: null);
        return \is_string($argument) && $argument !== '' ? $argument : null;
    }
    private function stringArgument(Attribute $attribute, string $name, int $position, string $default): string
    {
        $argument = $this->argument(attribute: $attribute, name: $name, position: $position, default: $default);
        return \is_string($argument) ? $argument : $default;
    }
    private function intArgument(Attribute $attribute, string $name, int $position, int $default): int
    {
        $argument = $this->argument(attribute: $attribute, name: $name, position: $position, default: $default);
        return \is_int($argument) ? $argument : $default;
    }
    private function boolArgument(Attribute $attribute, string $name, int $position, bool $default): bool
    {
        $argument = $this->argument(attribute: $attribute, name: $name, position: $position, default: $default);
        return \is_bool($argument) ? $argument : $default;
    }
    /**
     * @param list<string> $default
     * @return list<string>
     */
    private function stringListArgument(Attribute $attribute, string $name, int $position, array $default): array
    {
        $argument = $this->argument(attribute: $attribute, name: $name, position: $position, default: $default);
        if (\is_string($argument)) {
            return [$argument];
        }
        if (!\is_array($argument)) {
            return $default;
        }
        $values = [];
        foreach ($argument as $value) {
            if (\is_string($value)) {
                $values[] = $value;
            }
        }
        return $values;
    }
    private function argument(Attribute $attribute, string $name, int $position, mixed $default): mixed
    {
        foreach ($attribute->args as $index => $argument) {
            if ($argument->name?->toString() === $name || $argument->name === null && $index === $position) {
                return $this->value($argument->value);
            }
        }
        return $default;
    }
    private function value(Expr $expression): mixed
    {
        if ($expression instanceof Scalar\String_) {
            return $expression->value;
        }
        if ($expression instanceof Scalar\LNumber) {
            return $expression->value;
        }
        if ($expression instanceof Expr\ConstFetch) {
            return match (\strtolower($expression->name->toString())) {
                'true' => true,
                'false' => false,
                default => null,
            };
        }
        if ($expression instanceof Expr\Array_) {
            $values = [];
            foreach ($expression->items as $item) {
                $values[] = $this->value($item->value);
            }
            return $values;
        }
        return null;
    }
    private function typeName(Node $type): string
    {
        if ($type instanceof Node\NullableType) {
            return '?' . $this->typeName($type->type);
        }
        if ($type instanceof Node\UnionType) {
            return \implode(separator: '|', array: \array_map(fn(Node $unionType): string => $this->typeName($unionType), $type->types));
        }
        if ($type instanceof Name) {
            return $this->resolvedName($type);
        }
        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }
        return 'string';
    }
    private function baseTypeName(string $type): string
    {
        return \ltrim(string: \str_replace(search: '?', replace: '', subject: \explode(separator: '|', string: $type)[0]), characters: '\\');
    }
    private function resolvedName(Node $node): string
    {
        $resolvedName = $node->getAttribute('namespacedName') ?? $node->getAttribute('resolvedName');
        if ($resolvedName instanceof Name) {
            return $resolvedName->toString();
        }
        if ($node instanceof Name) {
            return $node->toString();
        }
        if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Enum_) {
            if ($node->namespacedName instanceof Name) {
                return $node->namespacedName->toString();
            }
            return $node->name?->toString() ?? '';
        }
        return '';
    }
    private function belongsToNamespace(string $className, string $apiNamespace): bool
    {
        return $className === $apiNamespace || \str_starts_with(haystack: $className, needle: $apiNamespace . '\\');
    }
    private function summaryFromDocComment(string|null $docComment): string
    {
        if ($docComment === null) {
            return '';
        }
        $lines = \explode(separator: "\n", string: $docComment);
        foreach ($lines as $line) {
            $cleanLine = \trim(\str_replace(search: ['/**', '*/', '*'], replace: '', subject: $line));
            if ($cleanLine === '' || \str_starts_with(haystack: $cleanLine, needle: '@')) {
                continue;
            }
            return $cleanLine;
        }
        return '';
    }
    private function listItemType(string|null $docComment): string|null
    {
        if ($docComment === null || !\str_contains(haystack: $docComment, needle: 'list<')) {
            return null;
        }
        $start = \strpos(haystack: $docComment, needle: 'list<');
        if ($start === false) {
            return null;
        }
        $tail = \substr(string: $docComment, offset: $start + 5);
        $end = \strpos(haystack: $tail, needle: '>');
        if ($end === false) {
            return null;
        }
        return \substr(string: $tail, offset: 0, length: $end);
    }
}
