<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Resource;

use App\Modules\Media\Public\Dto\MediaOriginalDto;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

/**
 * Оригинал медиа в ответе API: ссылка и срок её действия (для presigned-ссылки private-медиа,
 * иначе null). Отсутствие оригинала выражается как MediaResource.original = null.
 *
 * Собственная копия ресурса медиа: общий ресурс жил бы в чужом модуле и нарушал бы его границу, а
 * имя схемы OpenAPI строится по короткому имени класса, поэтому имя и поля повторяются дословно.
 */
final readonly class MediaOriginalResource extends AbstractResource
{
    public function __construct(
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}

    public static function fromDto(MediaOriginalDto $original): self
    {
        return new self(
            url: $original->url,
            expiresAt: $original->expiresAt,
        );
    }
}
