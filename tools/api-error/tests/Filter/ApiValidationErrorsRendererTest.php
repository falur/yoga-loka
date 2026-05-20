<?php

declare(strict_types=1);

namespace Tools\ApiError\Tests\Filter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tools\ApiError\Filter\ApiValidationErrorsRenderer;
use Tools\ApiError\Tests\Support\FakeTranslator;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;
use Tools\OpenApi\Response\Enum\HttpStatus;

final class ApiValidationErrorsRendererTest extends TestCase
{
    public function testRendererReturnsEnglishValidationErrorResponse(): void
    {
        $this->assertValidationErrorResponse(
            translator: self::englishTranslator(),
            expectedBody: '{"message":"Validation error","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}',
        );
    }

    public function testRendererReturnsRussianValidationErrorResponse(): void
    {
        $this->assertValidationErrorResponse(
            translator: new FakeTranslator(
                locale: 'ru',
                messages: [
                    'yoga_loka.api_error.validation_error' => 'Ошибка валидации',
                ],
            ),
            expectedBody: '{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}',
        );
    }

    private function assertValidationErrorResponse(FakeTranslator $translator, string $expectedBody): void
    {
        $response = (new ApiValidationErrorsRenderer(
            logger: new NullLogger(),
            translator: $translator,
        ))->render([
            'email' => 'Некорректный email',
            'password' => 'Пароль обязателен',
        ]);

        self::assertSame(HttpStatus::UnprocessableEntity->value, $response->getStatusCode());
        self::assertSame(ContentType::Json->value, $response->getHeaderLine(HttpHeader::ContentType->value));
        self::assertSame($expectedBody, (string) $response->getBody());
    }

    private static function englishTranslator(): FakeTranslator
    {
        return new FakeTranslator(
            locale: 'en',
            messages: [
                'yoga_loka.api_error.validation_error' => 'Validation error',
            ],
        );
    }
}
