<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Resource;

use App\Modules\Notifications\Application\Dto\NotificationSettingView;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Shared\Presentation\Http\Resource\AbstractResource;

/**
 * Строка экрана настроек: вид, канал (enum-ом — закрытый набор), эффективное значение и значение по
 * умолчанию. Человекочитаемое имя вида рисует клиент по коду type.
 */
final readonly class NotificationSettingResource extends AbstractResource
{
    public function __construct(
        public string $type,
        public NotificationChannel $channel,
        public bool $enabled,
        public bool $default,
    ) {}

    public static function fromView(NotificationSettingView $view): self
    {
        return new self(
            type: $view->type->value(),
            channel: $view->channel,
            enabled: $view->enabled,
            default: $view->default,
        );
    }
}
