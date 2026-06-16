<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Bootloader;

use Spiral\Bootloader\DomainBootloader;
use Spiral\Cycle\Interceptor\CycleInterceptor;
use Spiral\DataGrid\Interceptor\GridInterceptor;
use Spiral\Domain\GuardInterceptor;
use Psr\Clock\ClockInterface;
use Spiral\Interceptors\HandlerInterface;
use Symfony\Component\Clock\NativeClock;
use GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor;
use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;

/**
 * @link https://spiral.dev/docs/http-interceptors
 */
final class AppBootloader extends DomainBootloader
{
    protected const array INTERCEPTORS = [
        CycleInterceptor::class,
        GridInterceptor::class,
        GuardInterceptor::class,
        HttpResponseInterceptor::class,
        ApiExceptionInterceptor::class,
    ];

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            HandlerInterface::class => [self::class, 'domainCore'],
            ClockInterface::class => NativeClock::class,
        ];
    }
}
