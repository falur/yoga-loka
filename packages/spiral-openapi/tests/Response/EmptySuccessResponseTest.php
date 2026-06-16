<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Response;

use PHPUnit\Framework\TestCase;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;

final class EmptySuccessResponseTest extends TestCase
{
    public function testBuildsNoContentResponse(): void
    {
        $httpResponse = (new EmptySuccessResponse())->toResponse();

        self::assertSame(HttpStatus::NoContent->value, $httpResponse->getStatusCode());
        self::assertSame('', $httpResponse->getBody()->getContents());
        self::assertFalse($httpResponse->hasHeader(HttpHeader::ContentType->value));
    }
}
