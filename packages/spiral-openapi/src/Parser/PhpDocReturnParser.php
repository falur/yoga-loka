<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Parser;

use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\ParserConfig;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use GianTiaga\SpiralOpenApi\Model\GenericReturnType;

final readonly class PhpDocReturnParser
{
    private Lexer $lexer;
    private PhpDocParser $phpDocParser;
    public function __construct()
    {
        $parserConfig = new ParserConfig([]);
        $constExprParser = new ConstExprParser($parserConfig);
        $this->lexer = new Lexer($parserConfig);
        $this->phpDocParser = new PhpDocParser(config: $parserConfig, typeParser: new TypeParser(config: $parserConfig, constExprParser: $constExprParser), constantExprParser: $constExprParser);
    }
    /**
     * @param array<string, string> $aliases
     */
    public function parse(string|null $docComment, array $aliases, string $namespace): GenericReturnType|null
    {
        if ($docComment === null) {
            return null;
        }
        $phpDocNode = $this->phpDocParser->parse(new TokenIterator($this->lexer->tokenize($docComment)));
        foreach ($phpDocNode->getReturnTagValues() as $returnTagValue) {
            if (!$returnTagValue->type instanceof GenericTypeNode) {
                continue;
            }
            $genericType = $returnTagValue->type;
            $resourceType = $genericType->genericTypes[0] ?? null;
            if (!$resourceType instanceof TypeNode) {
                continue;
            }
            return new GenericReturnType(wrapperClass: $this->resolveTypeName(typeName: (string) $genericType->type, aliases: $aliases, namespace: $namespace), resourceClass: $this->resolveTypeName(typeName: $this->lastIdentifier($resourceType), aliases: $aliases, namespace: $namespace));
        }
        return null;
    }
    /**
     * @param array<string, string> $aliases
     */
    private function resolveTypeName(string $typeName, array $aliases, string $namespace): string
    {
        $trimmedTypeName = \ltrim(string: $typeName, characters: '\\');
        if (isset($aliases[$trimmedTypeName])) {
            return $aliases[$trimmedTypeName];
        }
        if (\str_contains(haystack: $trimmedTypeName, needle: '\\')) {
            return $trimmedTypeName;
        }
        return \sprintf('%s\%s', \trim(string: $namespace, characters: '\\'), $trimmedTypeName);
    }
    private function lastIdentifier(TypeNode $typeNode): string
    {
        if ($typeNode instanceof GenericTypeNode) {
            return $this->lastIdentifier($typeNode->genericTypes[0]);
        }
        if ($typeNode instanceof IdentifierTypeNode) {
            return (string) $typeNode;
        }
        return (string) $typeNode;
    }
}
