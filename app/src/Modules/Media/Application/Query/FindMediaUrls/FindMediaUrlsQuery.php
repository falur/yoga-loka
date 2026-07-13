<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrls;

/**
 * Пакетное разрешение URL сразу для нескольких медиа по их id. Аналог FindMediaUrl для потребителей,
 * которым нужен полный набор ссылок сразу для набора медиа (например, аватары авторов на странице
 * инбокса уведомлений) — одним запросом, без N+1.
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
