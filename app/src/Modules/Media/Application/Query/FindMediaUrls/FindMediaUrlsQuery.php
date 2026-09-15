<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrls;

/**
 * Пакетное разрешение URL сразу для нескольких медиа по их id: единственный путь модуля к полному
 * набору ссылок (оригинал + все конверсии) для потребителей, которым он нужен сразу для набора медиа
 * (например, аватары авторов на странице инбокса уведомлений) — одним запросом, без N+1.
 * Оговорка: невалидный переданный presignedTtlSeconds (например, явный 0) — ошибка входа,
 * она бросается, а не превращается в пропуск медиа.
 */
final readonly class FindMediaUrlsQuery
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public array $mediaIds,
        public int|null $presignedTtlSeconds = null,
    ) {}
}
