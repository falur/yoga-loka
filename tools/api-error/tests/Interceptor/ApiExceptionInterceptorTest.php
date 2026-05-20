<?php

declare(strict_types=1);

namespace Tools\ApiError\Tests\Interceptor;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Spiral\Filters\Exception\ValidationException as FilterValidationException;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Tools\ApiError\Interceptor\ApiExceptionInterceptor;
use Tools\ApiError\Tests\Support\FakeTranslator;
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
            translator: self::englishTranslator(),
        );
    }

    public function testDomainExceptionWith401CodeMapsTo401(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Нужна аутентификация', code: HttpStatus::Unauthorized->value),
            expectedStatus: HttpStatus::Unauthorized,
            expectedBody: '{"message":"Нужна аутентификация","code":401}',
            translator: self::englishTranslator(),
        );
    }

    public function testDomainExceptionWith403CodeMapsTo403(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Доступ запрещён', code: HttpStatus::Forbidden->value),
            expectedStatus: HttpStatus::Forbidden,
            expectedBody: '{"message":"Доступ запрещён","code":403}',
            translator: self::englishTranslator(),
        );
    }

    public function testDomainExceptionWith404CodeMapsTo404(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Не найдено', code: HttpStatus::NotFound->value),
            expectedStatus: HttpStatus::NotFound,
            expectedBody: '{"message":"Не найдено","code":404}',
            translator: self::englishTranslator(),
        );
    }

    public function testDomainExceptionWithOtherSupported4xxCodeMapsToSameCode(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Конфликт', code: HttpStatus::Conflict->value),
            expectedStatus: HttpStatus::Conflict,
            expectedBody: '{"message":"Конфликт","code":409}',
            translator: self::englishTranslator(),
        );
    }

    public function testDomainExceptionWithZeroCodeMapsTo400(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Доменная ошибка'),
            expectedStatus: HttpStatus::BadRequest,
            expectedBody: '{"message":"Доменная ошибка","code":400}',
            translator: self::englishTranslator(),
        );
    }

    public function testDomainExceptionWithUnsupported4xxCodeMapsTo400(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \DomainException(message: 'Неподдерживаемая ошибка', code: 499),
            expectedStatus: HttpStatus::BadRequest,
            expectedBody: '{"message":"Неподдерживаемая ошибка","code":400}',
            translator: self::englishTranslator(),
        );
    }

    public function testUnexpectedExceptionMapsTo500WithEnglishMessageWithoutInternalMessage(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \RuntimeException(message: 'SQL connection failed'),
            expectedStatus: HttpStatus::InternalServerError,
            expectedBody: '{"message":"Internal server error","code":500}',
            translator: self::englishTranslator(),
        );
    }

    public function testUnexpectedExceptionMapsTo500WithRussianMessageWithoutInternalMessage(): void
    {
        $this->assertExceptionMapsToResponse(
            exception: new \RuntimeException(message: 'SQL connection failed'),
            expectedStatus: HttpStatus::InternalServerError,
            expectedBody: '{"message":"Внутренняя ошибка сервера","code":500}',
            translator: new FakeTranslator(
                locale: 'ru',
                messages: [
                    'yoga_loka.api_error.internal_server_error' => 'Внутренняя ошибка сервера',
                ],
            ),
        );
    }

    public function testFilterValidationExceptionBubblesToValidationMiddleware(): void
    {
        $interceptor = new ApiExceptionInterceptor(
            logger: new NullLogger(),
            translator: self::englishTranslator(),
        );

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
        FakeTranslator $translator,
    ): void {
        $interceptor = new ApiExceptionInterceptor(
            logger: new NullLogger(),
            translator: $translator,
        );
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

    private static function englishTranslator(): FakeTranslator
    {
        return new FakeTranslator(
            locale: 'en',
            messages: [
                'yoga_loka.api_error.internal_server_error' => 'Internal server error',
            ],
        );
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
