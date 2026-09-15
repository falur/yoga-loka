<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\View;

/**
 * Оригинал медиа в read-model: ссылка и срок её действия (для presigned-ссылки private-медиа,
 * иначе null). Отсутствие оригинала выражается как MediaView.original = null.
 */
final readonly class MediaOriginalView
{
    public function __construct(
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}
}
