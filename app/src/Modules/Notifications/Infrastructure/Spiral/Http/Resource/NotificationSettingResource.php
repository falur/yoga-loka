<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Resource;

use App\Modules\Notifications\Application\Result\NotificationSettingResult;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

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

    public static function fromResult(NotificationSettingResult $setting): self
    {
        return new self(
            type: $setting->type->value(),
            channel: $setting->channel,
            enabled: $setting->enabled,
            default: $setting->default,
        );
    }
}
