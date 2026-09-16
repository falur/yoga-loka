<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Access;

use App\Shared\Infrastructure\Spiral\Http\Access\AccessRule;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Правило публичного маршрута: требований не предъявляет и пропускает запрос без личности.
 *
 * Правило существует ради самого объявления: зарегистрированный атрибут отличает намеренно
 * открытый маршрут от маршрута, забывшего объявить доступ.
 */
final readonly class PublicRouteRule implements AccessRule
{
    #[\Override]
    public function check(object $declaration, ServerRequestInterface $request): void
    {
        // Публичный маршрут пропускается без проверок.
    }
}
