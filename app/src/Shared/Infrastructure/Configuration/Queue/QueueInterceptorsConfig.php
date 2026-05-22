<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Queue;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Interceptors\InterceptorInterface;

final readonly class QueueInterceptorsConfig
{
    /**
     * @param list<class-string<InterceptorInterface>|class-string<CoreInterceptorInterface>|InterceptorInterface|CoreInterceptorInterface|Autowire<InterceptorInterface>|Autowire<CoreInterceptorInterface>> $push
     * @param list<class-string<InterceptorInterface>|class-string<CoreInterceptorInterface>|InterceptorInterface|CoreInterceptorInterface|Autowire<InterceptorInterface>|Autowire<CoreInterceptorInterface>> $consume
     */
    public function __construct(
        public array $push = [],
        public array $consume = [],
    ) {}
}
