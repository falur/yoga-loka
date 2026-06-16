<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

/**
 * Готовое push-сообщение для FCM: заголовок, текст, переход (deep-link) и снимок автора в
 * data-payload. actor — {id, name, avatarUrl} инициатора либо null (системное уведомление).
 */
final readonly class NotificationPush
{
    public function __construct(
        public string $title,
        public string $body,
        public NotificationActionPayload|null $action,
        public NotificationActorPayload|null $actor,
    ) {}
}
