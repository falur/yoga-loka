<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response;

use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
final readonly class HttpHeaderValue
{
    public function __construct(public HttpHeader $name, public string $value)
    {
    }
}
