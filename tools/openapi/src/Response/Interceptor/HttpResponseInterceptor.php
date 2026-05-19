<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response\Interceptor;

use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Tools\OpenApi\Response\ConvertsToHttpResponse;

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
