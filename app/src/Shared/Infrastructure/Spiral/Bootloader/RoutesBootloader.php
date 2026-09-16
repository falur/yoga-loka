<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Bootloader;

use App\Shared\Infrastructure\Spiral\Http\Access\AccessRuleRegistry;
use App\Shared\Infrastructure\Spiral\Http\Middleware\LocaleMiddleware;
use Spiral\Bootloader\Http\RoutesBootloader as BaseRoutesBootloader;
use Spiral\Cookies\Middleware\CookiesMiddleware;
use Spiral\Csrf\Middleware\CsrfMiddleware;
use Spiral\Debug\Middleware\DumperMiddleware;
use Spiral\Debug\StateCollector\HttpCollector;
use Spiral\Filter\ValidationHandlerMiddleware;
use Spiral\Http\Middleware\ErrorHandlerMiddleware;
use Spiral\Http\Middleware\JsonPayloadMiddleware;
use Spiral\Router\Bootloader\AnnotatedRoutesBootloader;
use Spiral\Session\Middleware\SessionMiddleware;
use GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware;

/**
 * Настраивает маршруты и middleware приложения, а также реестр правил доступа маршрутов:
 * реестр принадлежит общей части HTTP-границы, а наполняют его bootloader-ы модулей-владельцев доступа.
 *
 * @link https://spiral.dev/docs/http-routing
 */
final class RoutesBootloader extends BaseRoutesBootloader
{
    public const string GROUP_API = 'api';
    public const string GROUP_WEB = 'web';
    protected const array DEPENDENCIES = [AnnotatedRoutesBootloader::class];

    protected const array SINGLETONS = [AccessRuleRegistry::class => AccessRuleRegistry::class];

    #[\Override]
    protected function globalMiddleware(): array
    {
        return [
            ErrorHandlerMiddleware::class,
            LocaleMiddleware::class,
            RouteNotFoundMiddleware::class,
            DumperMiddleware::class,
            JsonPayloadMiddleware::class,
            HttpCollector::class,
        ];
    }

    #[\Override]
    protected function middlewareGroups(): array
    {
        return [
            self::GROUP_API => [
                ValidationHandlerMiddleware::class,
            ],
            self::GROUP_WEB => [
                CookiesMiddleware::class,
                SessionMiddleware::class,
                CsrfMiddleware::class,
                ValidationHandlerMiddleware::class,
            ],
        ];
    }
}
