<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * URL к медиа или его конверсии. expiresAt = null для прямого публичного URL (media-public),
 * заполнен для presigned-ссылки private-медиа.
 */
final readonly class MediaUrlResult
{
    public function __construct(
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}
}
