# HTTP Middleware

## Назначение

Middleware решает техническую задачу HTTP-границы до и после Controller: локаль, ограничение частоты, контекст запроса, установление личности. Требование доступа middleware не проверяет — его объявляет публичный атрибут маршрута.

## Когда применять

Применяй, когда решение принимается для всех маршрутов группы и не зависит от сценария. Бизнес-правило в middleware не выносится: оно остаётся в Application и Domain.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Middleware;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Auth\AuthContextInterface;

/**
 * Кладёт authUserId/authSessionId из payload access-токена в request-атрибуты, чтобы контроллер
 * читал их через Filter #[Attribute], а не ServerRequestInterface. При отсутствии/битом payload
 * или для refresh-токена атрибуты не ставятся (и middleware не падает).
 */
final readonly class AuthContextAttributeMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE_USER_ID = 'authUserId';
    public const string ATTRIBUTE_SESSION_ID = 'authSessionId';

    public function __construct(
        private AuthContextInterface $authContext,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->authContext->getToken();

        if ($token === null) {
            return $handler->handle($request);
        }

        $payload = $token->getPayload();

        if (($payload['type'] ?? null) !== AuthTokenType::Access->value) {
            return $handler->handle($request);
        }

        $userId = $payload['userID'] ?? null;
        $sessionId = $payload['sessionID'] ?? null;

        if (\is_string($userId) && \is_string($sessionId)) {
            // PSR-7 ServerRequestInterface::withAttribute объявляет первый параметр как $name,
            // но конкретные реализации (Nyholm — $attribute) расходятся в имени, поэтому named
            // arguments невозможны и используются позиционные (правило погашено в phpstan.neon).
            $request = $request
                ->withAttribute(self::ATTRIBUTE_USER_ID, $userId)
                ->withAttribute(self::ATTRIBUTE_SESSION_ID, $sessionId);
        }

        return $handler->handle($request);
    }
}
```

## Что повторять

- Класс называется `{Задача}Middleware`, реализует `MiddlewareInterface` и лежит в `Infrastructure/Spiral/Http/Middleware` своего модуля.
- Единственный метод — `process()`; он либо передаёт запрос дальше, либо возвращает готовый ответ.
- Имя request attribute хранится в константе и читается по ней, а не по строковому литералу.
- Middleware не возвращает отказ по доступу: требование объявляет публичный атрибут маршрута, а применяет его общий адаптер HTTP-границы до Controller — см. карточку [Публичный атрибут доступа](public-attribute.md).
- Если middleware всё же отдаёт готовый ответ (например ограничение частоты), текст ошибки берётся из переводов по ключу и отдаётся на языке пользователя.
- В ответе нет секретов, токенов и внутренних подробностей.
- Middleware не обращается к Repository, Reader и шине и не содержит доменных ветвлений.
- Бизнес-модуль подключает middleware `Auth` и `Access` не напрямую, а через публичный атрибут доступа.

## Допустимые варианты

Middleware может добавлять в запрос атрибут для последующих звеньев — тогда чтение этого атрибута описывает Filter. Middleware без владельца среди модулей (локаль, ограничение частоты) находится в `Shared/Infrastructure/Spiral/Http/Middleware`.
