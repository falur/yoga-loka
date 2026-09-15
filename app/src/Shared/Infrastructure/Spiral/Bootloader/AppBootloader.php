<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Bootloader;

use App\Shared\Domain\Locale\LocaleResolver;
use App\Shared\Infrastructure\Spiral\Configuration\Locale\LocaleConfig;
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
            LocaleResolver::class => [self::class, 'localeResolver'],
        ];
    }

    /**
     * Собирает доменный LocaleResolver из конфига локали. Конфиг читается здесь, в Infrastructure,
     * чтобы Application получал готовый сервис без знания про *Config.
     */
    protected static function localeResolver(LocaleConfig $localeConfig): LocaleResolver
    {
        return new LocaleResolver(supported: $localeConfig->supported, default: $localeConfig->default);
    }
}
