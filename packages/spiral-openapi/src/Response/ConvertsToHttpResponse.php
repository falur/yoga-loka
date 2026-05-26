<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response;

use Psr\Http\Message\ResponseInterface;
interface ConvertsToHttpResponse
{
    public function toResponse(): ResponseInterface;
}
