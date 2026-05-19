<?php

declare(strict_types=1);

namespace Tools\ApiError\Tests\Interceptor;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Spiral\Filters\Exception\ValidationException as FilterValidationException;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Tools\ApiError\Interceptor\ApiExceptionInterceptor;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;
use Tools\OpenApi\Response\Enum\HttpStatus;
use Tools\OpenApi\Response\ErrorResponse;

final class ApiExceptionInterceptorTest extends TestCase
{
    public function testDomainExceptionWith422CodeMapsTo422(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Некорректное значение', code: HttpStatus::UnprocessableEntity->value),
            expectedStatus: HttpStatus::UnprocessableEntity,
            expectedBody: '{"message":"Некорректное значение","code":422}',
        );
    }

    public function testDomainExceptionWith401CodeMapsTo401(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Нужна аутентификация', code: HttpStatus::Unauthorized->value),
            expectedStatus: HttpStatus::Unauthorized,
            expectedBody: '{"message":"Нужна аутентификация","code":401}',
        );
    }

    public function testDomainExceptionWith403CodeMapsTo403(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Доступ запрещён', code: HttpStatus::Forbidden->value),
            expectedStatus: HttpStatus::Forbidden,
            expectedBody: '{"message":"Доступ запрещён","code":403}',
        );
    }

    public function testDomainExceptionWith404CodeMapsTo404(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Не найдено', code: HttpStatus::NotFound->value),
            expectedStatus: HttpStatus::NotFound,
            expectedBody: '{"message":"Не найдено","code":404}',
        );
    }

    public function testDomainExceptionWithOtherSupported4xxCodeMapsToSameCode(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Конфликт', code: HttpStatus::Conflict->value),
            expectedStatus: HttpStatus::Conflict,
            expectedBody: '{"message":"Конфликт","code":409}',
        );
    }

    public function testDomainExceptionWithZeroCodeMapsTo400(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Доменная ошибка'),
            expectedStatus: HttpStatus::BadRequest,
            expectedBody: '{"message":"Доменная ошибка","code":400}',
        );
    }

    public function testDomainExceptionWithUnsupported4xxCodeMapsTo400(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Неподдерживаемая ошибка', code: 499),
            expectedStatus: HttpStatus::BadRequest,
            expectedBody: '{"message":"Неподдерживаемая ошибка","code":400}',
        );
    }

    public function testUnexpectedExceptionMapsTo500WithoutInternalMessage(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \RuntimeException(message: 'SQL connection failed'),
            expectedStatus: HttpStatus::InternalServerError,
            expectedBody: '{"message":"Внутренняя ошибка сервера","code":500}',
        );
    }

    public function testFilterValidationExceptionBubblesToValidationMiddleware(): void
    {
        $interceptor = new ApiExceptionInterceptor(logger: new NullLogger());

        $this->expectException(FilterValidationException::class);

        $interceptor->intercept(
            context: self::createStub(CallContextInterface::class),
            handler: new ApiExceptionInterceptorFixtureHandler(
                exception: new FilterValidationException(errors: ['age' => 'Возраст должен быть числом']),
            ),
        );
    }

    private function assertExceptionMapsToResponse(
        \Throwable $exception,
        HttpStatus $expectedStatus,
        string $expectedBody,
    ): void {
        $interceptor = new ApiExceptionInterceptor(logger: new NullLogger());
        $response = $interceptor->intercept(
            context: self::createStub(CallContextInterface::class),
            handler: new ApiExceptionInterceptorFixtureHandler(exception: $exception),
        );

        self::assertInstanceOf(ErrorResponse::class, $response);

        $httpResponse = $response->toResponse();

        self::assertSame($expectedStatus->value, $httpResponse->getStatusCode());
        self::assertSame(ContentType::Json->value, $httpResponse->getHeaderLine(HttpHeader::ContentType->value));
        self::assertSame($expectedBody, (string) $httpResponse->getBody());
    }
}

final readonly class ApiExceptionInterceptorFixtureHandler implements HandlerInterface
{
    public function __construct(
        private \Throwable $exception,
    ) {}

    public function handle(CallContextInterface $context): mixed
    {
        throw $this->exception;
    }
}
