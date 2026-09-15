# HTTP Middleware

## Назначение

Middleware решает техническую задачу HTTP-границы до и после Controller: локаль, ограничение частоты, контекст запроса, требование доступа.

## Когда применять

Применяй, когда решение принимается для всех маршрутов группы и не зависит от сценария. Бизнес-правило в middleware не выносится: оно остаётся в Application и Domain.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Middleware;

use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Translator\TranslatorInterface;

/**
 * Требует действующую сессию: без идентификатора пользователя в запросе отдаёт 401 сразу.
 * Middleware стоит вне цепочки интерсепторов контроллера, поэтому исключение здесь
 * в ответ не преобразуется и ответ собирается явно.
 */
final readonly class RequireAuthenticatedMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute(AuthContextAttributeMiddleware::ATTRIBUTE_USER_ID) === null) {
            $errorResponse = new ErrorResponse(
                message: $this->translator->trans(id: 'app.auth.unauthenticated', parameters: [], domain: 'auth'),
                code: HttpStatus::Unauthorized->value,
            );

            return $errorResponse->withStatus(HttpStatus::Unauthorized)->toResponse();
        }

        return $handler->handle($request);
    }
}
```

## Что повторять

- Класс называется `{Задача}Middleware`, реализует `MiddlewareInterface` и лежит в `Infrastructure/Spiral/Http/Middleware` своего модуля.
- Единственный метод — `process()`; он либо передаёт запрос дальше, либо возвращает готовый ответ.
- Имя request attribute хранится в константе и читается по ней, а не по строковому литералу.
- Текст ошибки берётся из переводов по ключу и отдаётся на языке пользователя.
- В ответе нет секретов, токенов и внутренних подробностей.
- Middleware не обращается к Repository, Reader и шине и не содержит доменных ветвлений.
- Бизнес-модуль подключает middleware `Auth` и `Access` не напрямую, а через публичный атрибут доступа — см. карточку [Публичный атрибут доступа](public-attribute.md).

## Допустимые варианты

Middleware может добавлять в запрос атрибут для последующих звеньев — тогда чтение этого атрибута описывает Filter. Middleware без владельца среди модулей (локаль, ограничение частоты) находится в `Shared/Infrastructure/Spiral/Http/Middleware`.
