<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaAttachable;

/**
 * Подтверждение, что медиа можно вложить: существует, принадлежит владельцу и готово (Ready).
 */
final readonly class MediaAttachableResult
{
    public function __construct(
        public string $mediaId,
    ) {}
}
