<?php

declare(strict_types=1);

namespace Tools\OpenApi\Tests\Response;

use PHPUnit\Framework\TestCase;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;
use Tools\OpenApi\Response\Enum\HttpStatus;
use Tools\OpenApi\Response\ErrorResponse;
use Tools\OpenApi\Response\ValidationErrorItemResponse;
use Tools\OpenApi\Response\ValidationErrorResponse;

final class ValidationErrorResponseTest extends TestCase
{
    public function testValidationErrorResponseSerializesErrors(): void
    {
        $response = (new ValidationErrorResponse(
            message: 'Ошибка валидации',
            code: HttpStatus::UnprocessableEntity->value,
            errors: [
                new ValidationErrorItemResponse(
                    field: 'email',
                    message: 'Некорректный email',
                ),
            ],
        ))->withStatus(HttpStatus::UnprocessableEntity);

        $httpResponse = $response->toResponse();

        self::assertSame(HttpStatus::UnprocessableEntity->value, $httpResponse->getStatusCode());
        self::assertSame(ContentType::Json->value, $httpResponse->getHeaderLine(HttpHeader::ContentType->value));
        self::assertJsonStringEqualsJsonString(
            '{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"}]}',
            (string) $httpResponse->getBody(),
        );
    }

    public function testValidationErrorResponseSerializesEmptyErrors(): void
    {
        $response = (new ValidationErrorResponse(
            message: 'Ошибка валидации',
            code: HttpStatus::UnprocessableEntity->value,
            errors: [],
        ))->withStatus(HttpStatus::UnprocessableEntity);

        self::assertJsonStringEqualsJsonString(
            '{"message":"Ошибка валидации","code":422,"errors":[]}',
            (string) $response->toResponse()->getBody(),
        );
    }

    public function testErrorResponseDoesNotContainErrorsField(): void
    {
        $response = (new ErrorResponse(
            message: 'Ошибка',
            code: HttpStatus::BadRequest->value,
        ))->withStatus(HttpStatus::BadRequest);

        self::assertJsonStringEqualsJsonString(
            '{"message":"Ошибка","code":400}',
            (string) $response->toResponse()->getBody(),
        );
    }
}
