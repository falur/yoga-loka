<?php

declare(strict_types=1);

namespace Tools\ApiError\Tests;

use PHPUnit\Framework\TestCase;
use Spiral\Translator\TranslatorInterface;
use Tools\ApiError\Filter\ApiValidationErrorsRenderer;
use Tools\ApiError\Interceptor\ApiExceptionInterceptor;
use Tools\ApiError\Middleware\RouteNotFoundMiddleware;

final class ConstructorContractTest extends TestCase
{
    public function testApiErrorClassesRequireTranslator(): void
    {
        $this->assertRequiredTranslatorParameter(
            className: RouteNotFoundMiddleware::class,
            parameterIndex: 1,
        );
        $this->assertRequiredTranslatorParameter(
            className: ApiValidationErrorsRenderer::class,
            parameterIndex: 1,
        );
        $this->assertRequiredTranslatorParameter(
            className: ApiExceptionInterceptor::class,
            parameterIndex: 1,
        );
    }

    /**
     * @param class-string $className
     */
    private function assertRequiredTranslatorParameter(string $className, int $parameterIndex): void
    {
        $constructor = (new \ReflectionClass($className))->getConstructor();
        self::assertNotNull($constructor);

        $parameter = $constructor->getParameters()[$parameterIndex] ?? null;
        self::assertNotNull($parameter);
        self::assertSame('translator', $parameter->getName());
        self::assertFalse($parameter->isOptional());

        $type = $parameter->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(TranslatorInterface::class, $type->getName());
    }
}
