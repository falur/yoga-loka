<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Dto;

/**
 * Готовое содержимое уведомления, которое модуль-источник передаёт в NotificationContract::send().
 * Только примитивы и публичные DTO: код вида (`module.action` — тот же, что вернуло определение
 * вида), заголовок и текст уже на языке получателя (Notifications их не переводит), переход
 * (deep-link) либо null, автор-инициатор либо null (системное уведомление).
 */
final readonly class NotificationContentDto
{
    public function __construct(
        public string $typeCode,
        public string $title,
        public string $body,
        public NotificationActionDto|null $action,
        public NotificationActorDto|null $actor,
    ) {}
}
