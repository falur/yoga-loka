<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Access;

use App\Modules\Auth\Infrastructure\Spiral\Http\Middleware\AuthContextAttributeMiddleware;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Infrastructure\Spiral\Http\Access\AccessRule;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Правило маршрута с действующей сессией: требует идентификатор пользователя в атрибутах запроса.
 *
 * Идентификатор кладёт middleware установления личности группы `api`. Его отсутствие означает,
 * что access-токен не передан или недействителен, и правило бросает доменное исключение
 * аутентификации — общий обработчик ошибок API превращает его в ответ 401.
 */
final readonly class AuthenticatedRouteRule implements AccessRule
{
    #[\Override]
    public function check(object $declaration, ServerRequestInterface $request): void
    {
        if ($request->getAttribute(AuthContextAttributeMiddleware::ATTRIBUTE_USER_ID) === null) {
            throw new AuthenticationException('app.auth.unauthenticated');
        }
    }
}
