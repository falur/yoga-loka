<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

use Tools\OpenApi\Response\Enum\HttpStatus;

trait HasHttpResponseMetadata
{
    private HttpStatus $status = HttpStatus::Ok;

    /**
     * @var null|array<string, list<string>>
     */
    private ?array $headers = null;

    public function withStatus(HttpStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function withHeader(HttpHeaderValue $header): static
    {
        $headers = $this->responseHeaders();
        $headers[$header->name->value] = [$header->value];
        $this->headers = $headers;

        return $this;
    }

    public function withAddedHeader(HttpHeaderValue $header): static
    {
        $headers = $this->responseHeaders();
        $headers[$header->name->value][] = $header->value;
        $this->headers = $headers;

        return $this;
    }

    public function setHeaders(HttpHeaderValue ...$headers): static
    {
        $responseHeaders = [];

        foreach ($headers as $header) {
            $responseHeaders[$header->name->value] = [$header->value];
        }

        $this->headers = $responseHeaders;

        return $this;
    }

    protected function responseStatus(): HttpStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function responseHeaders(): array
    {
        return $this->headers ?? $this->defaultHeaders();
    }

    /**
     * @return array<string, list<string>>
     */
    protected function defaultHeaders(): array
    {
        return [];
    }
}
