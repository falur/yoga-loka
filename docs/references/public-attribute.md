# Публичный атрибут доступа

## Назначение

Маршрут объявляет требуемый доступ атрибутом из `Public` модуля-владельца доступа. Общий HTTP-адаптер применяет атрибут до Controller, поэтому бизнес-модуль не импортирует middleware и внутренние обработчики `Auth` и `Access`.

## Когда применять

Применяй на каждом HTTP-маршруте: публичный маршрут, маршрут с действующей сессией или маршрут с конкретным правом. Сессиями и токенами владеет `Auth`, ролями и правами — `Access`.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Public\Attribute;

/** Маршрут доступен только с действующей сессией. */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class AuthenticatedRoute {}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Access\Public\Attribute;

use App\Modules\Access\Public\Enum\PublicPermission;

/** Маршрут доступен только обладателю указанного права. */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class RequiresPermission
{
    public function __construct(
        public PublicPermission $permission,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Controller;

use App\Modules\Access\Public\Attribute\RequiresPermission;
use App\Modules\Access\Public\Enum\PublicPermission;
use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\Posts\Application\Command\BlockPost\BlockPostCommand;
use App\Modules\Posts\Application\Command\BlockPost\BlockPostHandler;
use App\Modules\Posts\Infrastructure\Spiral\Http\Filter\BlockPostFilter;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use Spiral\Router\Annotation\Route;

final readonly class PostModerationController
{
    #[Route(
        route: '/api/v1/posts/<postId>/block',
        name: 'api.v1.posts.block',
        methods: ['POST'],
        group: 'api',
    )]
    #[AuthenticatedRoute]
    #[RequiresPermission(permission: PublicPermission::BlockPost)]
    public function block(
        BlockPostFilter $blockPostFilter,
        BlockPostHandler $blockPostHandler,
        CommandBusInterface $commandBus,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new BlockPostCommand(
                postId: $blockPostFilter->postId,
                moderatorUserId: $blockPostFilter->authUserId,
            ),
            handler: $blockPostHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }
}
```

## Что повторять

- Атрибут лежит в `Public/Attribute` модуля-владельца доступа и называет требование маршрута, а не реализацию проверки.
- Класс `final readonly`, помечен `#[\Attribute]`; параметры — только скаляры и публичные enum того же `Public`.
- Маршрут соседнего модуля объявляет доступ этим атрибутом и ничего больше про `Auth` и `Access` не знает.
- Атрибут применяет общий HTTP-адаптер до Controller; проверка внутри Controller и handler не дублируется.
- Доменная проверка владения ресурсом остаётся в сценарии целевого модуля: атрибут отвечает за «кто вошёл и что ему разрешено», а не за «его ли это запись».
- Отсутствие атрибута не означает публичный маршрут — публичность объявляется явно.

## Допустимые варианты

Требования складываются: `AuthenticatedRoute` плюс `RequiresPermission` на одном методе. Публичный маршрут помечается собственным атрибутом `Auth`, чтобы забытая декларация отличалась от намеренно открытого доступа.
