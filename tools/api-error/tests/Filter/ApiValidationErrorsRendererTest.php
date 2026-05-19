<?php

declare(strict_types=1);

namespace Tools\ApiError\Tests\Filter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tools\ApiError\Filter\ApiValidationErrorsRenderer;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;
use Tools\OpenApi\Response\Enum\HttpStatus;

final class ApiValidationErrorsRendererTest extends TestCase
{
    public function testRendererReturnsValidationErrorResponse(): void
    {
        $response = (new ApiValidationErrorsRenderer(logger: new NullLogger()))->render([
            'email' => 'Некорректный email',
            'password' => 'Пароль обязателен',
        ]);

        self::assertSame(HttpStatus::UnprocessableEntity->value, $response->getStatusCode());
        self::assertSame(ContentType::Json->value, $response->getHeaderLine(HttpHeader::ContentType->value));
        self::assertSame(
            '{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}',
            (string) $response->getBody(),
        );
    }
}
