<?php

declare (strict_types=1);

namespace GianTiaga\SpiralApiErrors\Tests\Filter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
use GianTiaga\SpiralApiErrors\Tests\Support\FakeTranslator;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
final class ApiValidationErrorsRendererTest extends TestCase
{
    public function testRendererReturnsEnglishValidationErrorResponse(): void
    {
        $this->assertValidationErrorResponse(translator: self::englishTranslator(), expectedBody: '{"message":"Validation error","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}');
    }
    public function testRendererReturnsRussianValidationErrorResponse(): void
    {
        $this->assertValidationErrorResponse(translator: new FakeTranslator(locale: 'ru', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Ошибка валидации']), expectedBody: '{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}');
    }
    private function assertValidationErrorResponse(FakeTranslator $translator, string $expectedBody): void
    {
        $response = (new ApiValidationErrorsRenderer(logger: new NullLogger(), translator: $translator))->render(['email' => 'Некорректный email', 'password' => 'Пароль обязателен']);
        self::assertSame(HttpStatus::UnprocessableEntity->value, $response->getStatusCode());
        self::assertSame(ContentType::Json->value, $response->getHeaderLine(HttpHeader::ContentType->value));
        self::assertSame($expectedBody, (string) $response->getBody());
    }
    private static function englishTranslator(): FakeTranslator
    {
        return new FakeTranslator(locale: 'en', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Validation error']);
    }
}
