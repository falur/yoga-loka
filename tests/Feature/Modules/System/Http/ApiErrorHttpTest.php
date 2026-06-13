<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\System\Http;

use App\Shared\Infrastructure\Framework\Bootloader\AppBootloader;
use App\Shared\Infrastructure\Framework\Bootloader\RoutesBootloader;
use App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware;
use Spiral\Filters\ErrorsRendererInterface;
use Spiral\Http\Middleware\ErrorHandlerMiddleware;
use Tests\TestCase;
use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
use GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor;
use GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;

final class ApiErrorHttpTest extends TestCase
{
    public function testApiRouteWithDomainExceptionReturnsJsonError(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->getJson('/test/api/errors/domain');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Тестовый ресурс не найден.","code":404}');
    }

    public function testApiRouteWithInvalidDomainValueExceptionReturnsInternalServerError(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->getJson('/test/api/errors/invalid-domain-value');

        $response->assertStatus(500);
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Внутренняя ошибка сервера","code":500}');
    }

    public function testApiFilterValidationReturnsEnglishJsonErrorWithErrors(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'en')
            ->postJson('/test/api/errors/filter', [
                'age' => 'abc',
            ]);

        $response->assertUnprocessable();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame(
            '{"message":"Validation error","code":422,"errors":[{"field":"age","message":"Возраст должен быть числом"}]}',
        );
    }

    public function testApiFilterValidationReturnsRussianJsonErrorWithErrors(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->postJson('/test/api/errors/filter', [
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

    public function testUnknownRouteReturnsEnglishJsonRouteNotFoundError(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'en')
            ->getJson('/test/api/errors/missing');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Route not found.","code":404}');
    }

    public function testUnknownRouteReturnsRussianJsonRouteNotFoundError(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->getJson('/test/api/errors/missing');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Маршрут не найден.","code":404}');
    }

    public function testWrongMethodReturnsEnglishJsonRouteNotFoundError(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'en')
            ->postJson('/test/api/errors/domain');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Route not found.","code":404}');
    }

    public function testLocaleMiddlewareIsRegisteredBetweenErrorHandlerAndRouteNotFoundMiddleware(): void
    {
        $middleware = (new \ReflectionClass(RoutesBootloader::class))
            ->getMethod('globalMiddleware')
            ->invoke(new RoutesBootloader());

        self::assertIsArray($middleware);
        self::assertSame(ErrorHandlerMiddleware::class, $middleware[0] ?? null);
        self::assertSame(LocaleMiddleware::class, $middleware[1] ?? null);
        self::assertSame(RouteNotFoundMiddleware::class, $middleware[2] ?? null);
    }
}
