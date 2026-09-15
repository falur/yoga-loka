<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\View;

/**
 * Read-model уведомления инбокса для ответа API. title/body — как сохранено (без перевода), action —
 * переход либо null, actor — снимок автора с аватаром-MediaDto либо null. Собирается
 * NotificationViewAssembler, который обогащает снимок автора актуальным аватаром через модуль Media.
 */
final readonly class NotificationView
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $body,
        public NotificationActionView|null $action,
        public NotificationActorView|null $actor,
        public bool $read,
        public \DateTimeImmutable $createdAt,
    ) {}
}
