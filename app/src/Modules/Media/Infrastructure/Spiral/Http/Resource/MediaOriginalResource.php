<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Spiral\Http\Resource;

use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;
use App\Modules\Media\Application\View\MediaOriginalView;

/**
 * Оригинал медиа в ответе API: ссылка и срок её действия (для presigned-ссылки private-медиа,
 * иначе null). Отсутствие оригинала выражается как MediaResource.original = null.
 */
final readonly class MediaOriginalResource extends AbstractResource
{
    public function __construct(
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}

    public static function fromView(MediaOriginalView $original): self
    {
        return new self(
            url: $original->url,
            expiresAt: $original->expiresAt,
        );
    }
}
