<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;

abstract class AbstractJsonResponse implements \JsonSerializable, ConvertsToHttpResponse
{
    use HasHttpResponseMetadata;
    final public function jsonSerialize(): mixed
    {
        $payload = [];
        foreach ((new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $payload[$property->getName()] = $property->getValue(object: $this);
        }
        return $payload;
    }
    public function toResponse(): ResponseInterface
    {
        return new Response(status: $this->responseStatus()->value, headers: $this->responseHeaders(), body: \json_encode(value: $this, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
    /**
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        return [HttpHeader::ContentType->value => [ContentType::Json->value]];
    }
}
