<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Response;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use GianTiaga\SpiralOpenApi\Response\ConvertsToHttpResponse;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\HttpHeaderValue;
use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;
use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
final class JsonResponseTest extends TestCase
{
    public function testPaginationResponseBuildsHttpResponseWithDefaultJsonHeader(): void
    {
        $response = new PaginationResponse(data: [new JsonResponseFixtureResource(name: 'Анна')], meta: new PaginationMetaResponse(nextCursor: 'next-cursor', limit: 20));
        $httpResponse = $response->toResponse();
        self::assertSame(HttpStatus::Ok->value, $httpResponse->getStatusCode());
        self::assertSame(ContentType::Json->value, $httpResponse->getHeaderLine(HttpHeader::ContentType->value));
        self::assertJsonStringEqualsJsonString('{"data":[{"name":"Анна"}],"meta":{"nextCursor":"next-cursor","limit":20}}', (string) $httpResponse->getBody());
    }
    public function testPaginationResponseCanOverrideStatusAndHeaders(): void
    {
        $response = (new PaginationResponse(data: [], meta: new PaginationMetaResponse(nextCursor: null, limit: 20)))->withHeader(new HttpHeaderValue(name: HttpHeader::XRequestId, value: 'request-1'))->withAddedHeader(new HttpHeaderValue(name: HttpHeader::Vary, value: 'Accept'))->withAddedHeader(new HttpHeaderValue(name: HttpHeader::Vary, value: 'Origin'))->withStatus(HttpStatus::Created);
        $httpResponse = $response->toResponse();
        self::assertSame(HttpStatus::Created->value, $httpResponse->getStatusCode());
        self::assertSame('request-1', $httpResponse->getHeaderLine(HttpHeader::XRequestId->value));
        self::assertSame(['Accept', 'Origin'], $httpResponse->getHeader(HttpHeader::Vary->value));
    }
    public function testSetHeadersReplacesDefaultJsonHeaders(): void
    {
        $response = (new PaginationResponse(data: [], meta: new PaginationMetaResponse(nextCursor: null, limit: 20)))->setHeaders(new HttpHeaderValue(name: HttpHeader::CacheControl, value: 'no-store'));
        $httpResponse = $response->toResponse();
        self::assertFalse($httpResponse->hasHeader(HttpHeader::ContentType->value));
        self::assertSame('no-store', $httpResponse->getHeaderLine(HttpHeader::CacheControl->value));
    }
    public function testHttpResponseInterceptorConvertsOpenApiResponse(): void
    {
        $interceptor = new HttpResponseInterceptor();
        $response = new PaginationResponse(data: [], meta: new PaginationMetaResponse(nextCursor: null, limit: 20));
        $httpResponse = $interceptor->intercept(context: self::createStub(CallContextInterface::class), handler: new JsonResponseFixtureHandler($response));
        self::assertInstanceOf(ResponseInterface::class, $httpResponse);
        self::assertSame(HttpStatus::Ok->value, $httpResponse->getStatusCode());
        self::assertSame(ContentType::Json->value, $httpResponse->getHeaderLine(HttpHeader::ContentType->value));
    }
    public function testHttpResponseInterceptorIgnoresOtherResults(): void
    {
        $interceptor = new HttpResponseInterceptor();
        self::assertSame('plain result', $interceptor->intercept(context: self::createStub(CallContextInterface::class), handler: new JsonResponseFixtureHandler('plain result')));
    }
}
final readonly class JsonResponseFixtureResource implements \JsonSerializable
{
    public function __construct(public string $name)
    {
    }
    public function jsonSerialize(): mixed
    {
        return \get_object_vars($this);
    }
}
final readonly class JsonResponseFixtureHandler implements HandlerInterface
{
    public function __construct(private ConvertsToHttpResponse|string $response)
    {
    }
    public function handle(CallContextInterface $context): mixed
    {
        return $this->response;
    }
}
