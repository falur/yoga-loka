<?php

declare(strict_types=1);

namespace Tests\App\Bootloader;

use App\Shared\Infrastructure\Framework\Bootloader\RoutesBootloader as AppRoutesBootloader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Router\Loader\Configurator\RoutingConfigurator;
use Tests\App\Modules\System\Http\ApiErrorTestController;

final class ApiErrorTestRoutesBootloader extends Bootloader
{
    public function boot(RoutingConfigurator $routes): void
    {
        $routes
            ->add(name: 'test.api.error.domain', pattern: '/test/api/errors/domain')
            ->action(controller: ApiErrorTestController::class, action: 'domain')
            ->methods(methods: 'GET')
            ->group(group: AppRoutesBootloader::GROUP_API);

        $routes
            ->add(name: 'test.api.error.parametrized', pattern: '/test/api/errors/parametrized')
            ->action(controller: ApiErrorTestController::class, action: 'parametrizedDomain')
            ->methods(methods: 'GET')
            ->group(group: AppRoutesBootloader::GROUP_API);

        $routes
            ->add(name: 'test.api.error.invalid-domain-value', pattern: '/test/api/errors/invalid-domain-value')
            ->action(controller: ApiErrorTestController::class, action: 'invalidDomainValue')
            ->methods(methods: 'GET')
            ->group(group: AppRoutesBootloader::GROUP_API);

        $routes
            ->add(name: 'test.api.error.filter', pattern: '/test/api/errors/filter')
            ->action(controller: ApiErrorTestController::class, action: 'filter')
            ->methods(methods: 'POST')
            ->group(group: AppRoutesBootloader::GROUP_API);
    }
}
