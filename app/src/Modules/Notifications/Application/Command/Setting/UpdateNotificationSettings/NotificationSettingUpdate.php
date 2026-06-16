<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings;

/**
 * Один пункт обновления настроек «вид × канал» на внешней границе (примитивы). Handler сам строит
 * VO/enum и проверяет вид через реестр, а канал — через enum.
 */
final readonly class NotificationSettingUpdate
{
    public function __construct(
        public string $type,
        public string $channel,
        public bool $enabled,
    ) {}
}
