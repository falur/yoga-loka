<?php

declare(strict_types=1);

namespace Tests\Feature\Endpoint\Api;

use App\Infrastructure\Framework\Bootloader\AppBootloader;
use App\Infrastructure\Framework\Bootloader\RoutesBootloader;
use Spiral\Filters\ErrorsRendererInterface;
use Spiral\Http\Middleware\ErrorHandlerMiddleware;
use Tests\TestCase;
use Tools\ApiError\Filter\ApiValidationErrorsRenderer;
use Tools\ApiError\Interceptor\ApiExceptionInterceptor;
use Tools\ApiError\Middleware\RouteNotFoundMiddleware;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;
use Tools\OpenApi\Response\Interceptor\HttpResponseInterceptor;

final class ApiErrorHttpTest extends TestCase
{
    public function testApiRouteWithDomainExceptionReturnsJsonError(): void
    {
        $response = $this->fakeHttp()->getJson('/test/api/errors/domain');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Тестовый ресурс не найден.","code":404}');
    }

    public function testApiFilterValidationReturnsJsonErrorWithErrors(): void
    {
        $response = $this->fakeHttp()->postJson('/test/api/errors/filter', [
            'age' => 'abc',
        ]);

        $response->assertUnprocessable();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame(
            '{"message":"Ошибка валидации","code":422,"errors":[{"field":"age","message":"Возраст должен быть числом"}]}',
        );
    }

    public function testValidationHandlerMiddlewareUsesApiValidationErrorsRenderer(): void
    {
        self::assertInstanceOf(
            ApiValidationErrorsRenderer::class,
            $this->getContainer()->get(ErrorsRendererInterface::class),
        );
    }

    public function testApiExceptionInterceptorIsRegisteredAfterHttpResponseInterceptor(): void
    {
        $interceptors = (new \ReflectionClass(AppBootloader::class))
            ->getReflectionConstant('INTERCEPTORS')
            ?->getValue();

        self::assertIsArray($interceptors);

        $httpResponseInterceptorPosition = \array_search(HttpResponseInterceptor::class, $interceptors, true);
        $apiExceptionInterceptorPosition = \array_search(ApiExceptionInterceptor::class, $interceptors, true);

        self::assertIsInt($httpResponseInterceptorPosition);
        self::assertIsInt($apiExceptionInterceptorPosition);
        self::assertGreaterThan($httpResponseInterceptorPosition, $apiExceptionInterceptorPosition);
    }

    public function testUnknownRouteReturnsJsonRouteNotFoundError(): void
    {
        $response = $this->fakeHttp()->getJson('/test/api/errors/missing');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Маршрут не найден.","code":404}');
    }

    public function testWrongMethodReturnsJsonRouteNotFoundError(): void
    {
        $response = $this->fakeHttp()->postJson('/test/api/errors/domain');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Маршрут не найден.","code":404}');
    }

    public function testRouteNotFoundMiddlewareIsRegisteredAfterErrorHandlerMiddleware(): void
    {
        $middleware = (new \ReflectionClass(RoutesBootloader::class))
            ->getMethod('globalMiddleware')
            ->invoke(new RoutesBootloader());

        self::assertIsArray($middleware);
        self::assertSame(ErrorHandlerMiddleware::class, $middleware[0] ?? null);
        self::assertSame(RouteNotFoundMiddleware::class, $middleware[1] ?? null);
    }
}
