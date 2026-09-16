<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Http\Access\Fixture;

use Spiral\Router\Annotation\Route;

/**
 * Контроллер-дублёр с маршрутом, забывшим объявить доступ.
 *
 * Живёт в тестах, а не в `app/src`: настоящий маршрут без объявления доступа запрещён
 * архитектурной проверкой и изменил бы список маршрутов приложения.
 */
final class UndeclaredAccessControllerFixture
{
    #[Route(
        route: '/kernel-fixture/undeclared',
        name: 'kernel_fixture.undeclared',
        methods: ['GET'],
        group: 'api',
    )]
    public function undeclared(): string
    {
        return 'контроллер не должен быть вызван';
    }
}
