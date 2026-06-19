<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

/**
 * Элемент медиа-вложения записи в read-model: идентификатор медиа, URL для показа и позиция.
 */
final readonly class PostMediaItemView
{
    public function __construct(
        public string $mediaId,
        public string $url,
        public int $position,
    ) {}
}
