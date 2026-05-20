<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;

final class FileContentResponse implements ConvertsToHttpResponse
{
    use HasHttpResponseMetadata;

    public function __construct(
        private readonly string $content,
        private readonly ContentType $contentType,
    ) {}

    public function toResponse(): ResponseInterface
    {
        return new Response(
            status: $this->responseStatus()->value,
            headers: $this->responseHeaders(),
            body: $this->content,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    protected function defaultHeaders(): array
    {
        return [
            HttpHeader::ContentType->value => [$this->contentType->value],
            HttpHeader::ContentDisposition->value => ['inline'],
        ];
    }
}
