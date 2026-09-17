<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Http\Access\Fixture;

use Spiral\Router\Annotation\Route;

/**
 * Контроллер-дублёр с четырьмя видами методов: маршрут с объявлением доступа, маршрут без
 * объявления, маршрут с атрибутом без зарегистрированного правила и метод вне маршрута.
 */
final class AccessRouteControllerFixture
{
    #[Route(route: '/fixture/declared', name: 'fixture.declared', methods: ['GET'], group: 'api')]
    #[AccessDeclarationFixture(name: 'объявленный маршрут')]
    public function declared(): string
    {
        return 'объявленный маршрут';
    }

    #[Route(route: '/fixture/undeclared', name: 'fixture.undeclared', methods: ['GET'], group: 'api')]
    public function undeclared(): string
    {
        return 'маршрут без объявления';
    }

    #[Route(
        route: '/fixture/undeclared-with-unregistered-attribute',
        name: 'fixture.undeclared_with_unregistered_attribute',
        methods: ['GET'],
        group: 'api',
    )]
    #[UnregisteredDeclarationFixture]
    public function undeclaredWithUnregisteredAttribute(): string
    {
        return 'маршрут с незарегистрированным атрибутом';
    }

    public function withoutRoute(): string
    {
        return 'метод вне маршрута';
    }
}
