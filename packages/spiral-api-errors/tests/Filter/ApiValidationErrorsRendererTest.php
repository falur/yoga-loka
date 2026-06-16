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
        $this->assertValidationErrorResponse(translator: self::englishTranslator(), expectedBody: '{"message":"Validation error","code":422,"errors":[{"field":"email","messages":["Некорректный email"]},{"field":"password","messages":["Пароль обязателен"]}]}');
    }
    public function testRendererReturnsRussianValidationErrorResponse(): void
    {
        $this->assertValidationErrorResponse(translator: new FakeTranslator(locale: 'ru', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Ошибка валидации']), expectedBody: '{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","messages":["Некорректный email"]},{"field":"password","messages":["Пароль обязателен"]}]}');
    }
    public function testRendererReturnsAllMessagesWhenFieldErrorsAreList(): void
    {
        // Symfony-валидатор отдаёт сообщения поля списком, нарушая статический контракт
        // интерфейса array<string,string>; сужаем mixed на границе так же, как делает рантайм,
        // и возвращаем все сообщения поля.
        $response = (new ApiValidationErrorsRenderer(logger: new NullLogger(), translator: self::englishTranslator()))->render($this->fieldErrors('{"nickname":["Никнейм имеет неверный формат","Никнейм не должен содержать две точки подряд"]}'));
        self::assertSame(HttpStatus::UnprocessableEntity->value, $response->getStatusCode());
        self::assertSame('{"message":"Validation error","code":422,"errors":[{"field":"nickname","messages":["Никнейм имеет неверный формат","Никнейм не должен содержать две точки подряд"]}]}', (string) $response->getBody());
    }
    public function testRendererReturnsEmptyListWhenFieldErrorListIsEmpty(): void
    {
        $response = (new ApiValidationErrorsRenderer(logger: new NullLogger(), translator: self::englishTranslator()))->render($this->fieldErrors('{"nickname":[]}'));
        self::assertSame(HttpStatus::UnprocessableEntity->value, $response->getStatusCode());
        self::assertSame('{"message":"Validation error","code":422,"errors":[{"field":"nickname","messages":[]}]}', (string) $response->getBody());
    }
    /**
     * @return array<string, string>
     */
    private function fieldErrors(string $json): array
    {
        /** @var array<string, string> $errors */
        $errors = \json_decode(json: $json, associative: true);
        return $errors;
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
