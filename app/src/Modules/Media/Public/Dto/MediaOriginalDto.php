<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Dto;

/**
 * Оригинал медиа в межмодульном ответе: ссылка и срок её действия (для presigned-ссылки
 * private-медиа, иначе null). Отсутствие оригинала выражается как MediaDto.original = null.
 */
final readonly class MediaOriginalDto
{
    public function __construct(
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}
}
