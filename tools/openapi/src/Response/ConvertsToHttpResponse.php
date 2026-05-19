<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

use Psr\Http\Message\ResponseInterface;

interface ConvertsToHttpResponse
{
    public function toResponse(): ResponseInterface;
}
