<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response\Interceptor;

use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use GianTiaga\SpiralOpenApi\Response\ConvertsToHttpResponse;
final readonly class HttpResponseInterceptor implements InterceptorInterface
{
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $response = $handler->handle($context);
        if (!$response instanceof ConvertsToHttpResponse) {
            return $response;
        }
        return $response->toResponse();
    }
}
