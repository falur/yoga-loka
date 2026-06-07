<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Response;

use PHPUnit\Framework\TestCase;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use GianTiaga\SpiralOpenApi\Response\ValidationErrorItemResponse;
use GianTiaga\SpiralOpenApi\Response\ValidationErrorResponse;

final class ValidationErrorResponseTest extends TestCase
{
    public function testValidationErrorResponseSerializesErrors(): void
    {
        $response = (new ValidationErrorResponse(message: 'Ошибка валидации', code: HttpStatus::UnprocessableEntity->value, errors: [new ValidationErrorItemResponse(field: 'email', message: 'Некорректный email')]))->withStatus(HttpStatus::UnprocessableEntity);
        $httpResponse = $response->toResponse();
        self::assertSame(HttpStatus::UnprocessableEntity->value, $httpResponse->getStatusCode());
        self::assertSame(ContentType::Json->value, $httpResponse->getHeaderLine(HttpHeader::ContentType->value));
        self::assertJsonStringEqualsJsonString('{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"}]}', (string) $httpResponse->getBody());
    }
    public function testValidationErrorResponseSerializesEmptyErrors(): void
    {
        $response = (new ValidationErrorResponse(message: 'Ошибка валидации', code: HttpStatus::UnprocessableEntity->value, errors: []))->withStatus(HttpStatus::UnprocessableEntity);
        self::assertJsonStringEqualsJsonString('{"message":"Ошибка валидации","code":422,"errors":[]}', (string) $response->toResponse()->getBody());
    }
    public function testErrorResponseDoesNotContainErrorsField(): void
    {
        $response = (new ErrorResponse(message: 'Ошибка', code: HttpStatus::BadRequest->value))->withStatus(HttpStatus::BadRequest);
        self::assertJsonStringEqualsJsonString('{"message":"Ошибка","code":400}', (string) $response->toResponse()->getBody());
    }
}
