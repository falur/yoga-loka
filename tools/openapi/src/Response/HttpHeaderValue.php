<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

use Tools\OpenApi\Response\Enum\HttpHeader;

final readonly class HttpHeaderValue
{
    public function __construct(
        public HttpHeader $name,
        public string $value,
    ) {}
}
