<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Resource;

use App\Modules\Notifications\Application\View\NotificationActionView;
use App\Shared\Presentation\Http\Resource\AbstractResource;

/**
 * Вложенный ресурс перехода (deep-link). Отдельный DTO, а не inline array-shape, чтобы OpenAPI-парсер
 * не схлопнул union. Создаётся только когда переход есть.
 */
final readonly class NotificationActionResource extends AbstractResource
{
    public function __construct(
        public string $actionType,
        public string $actionId,
    ) {}

    public static function fromView(NotificationActionView $action): self
    {
        return new self(
            actionType: $action->actionType,
            actionId: $action->actionId,
        );
    }
}
