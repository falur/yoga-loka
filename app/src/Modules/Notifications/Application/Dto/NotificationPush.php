<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;

/**
 * Готовое push-сообщение для FCM: заголовок, текст, переход (deep-link) и снимок автора в
 * data-payload. actor — {id, name, avatarUrl} инициатора либо null (системное уведомление); аватар —
 * одна ссылка (FCM data плоская), уже разрешённая из id медиа к моменту отправки.
 */
final readonly class NotificationPush
{
    public function __construct(
        public string $title,
        public string $body,
        public NotificationActionDto|null $action,
        public NotificationPushActorPayload|null $actor,
    ) {}
}
