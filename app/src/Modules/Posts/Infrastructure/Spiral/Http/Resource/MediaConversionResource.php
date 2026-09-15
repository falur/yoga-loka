<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Resource;

use App\Modules\Media\Public\Dto\MediaConversionDto;
use App\Modules\Media\Public\Enum\MediaAudioConversionType;
use App\Modules\Media\Public\Enum\MediaConversionKind;
use App\Modules\Media\Public\Enum\MediaImageConversionType;
use App\Modules\Media\Public\Enum\MediaVideoConversionType;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

/**
 * Одна конверсия медиа в ответе API: вид (image/video/audio) и тип-профиль — enum-ами (в JSON
 * сериализуются в свои строковые значения, а в OpenAPI дают закрытый набор), ссылка и срок действия
 * (для presigned-ссылок private-медиа, иначе null).
 *
 * Собственная копия ресурса медиа: общий ресурс жил бы в чужом модуле и нарушал бы его границу, а
 * имя схемы OpenAPI строится по короткому имени класса, поэтому имя и поля повторяются дословно.
 */
final readonly class MediaConversionResource extends AbstractResource
{
    public function __construct(
        public MediaConversionKind $kind,
        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}

    public static function fromDto(MediaConversionDto $conversion): self
    {
        return new self(
            kind: $conversion->kind,
            type: $conversion->type,
            url: $conversion->url,
            expiresAt: $conversion->expiresAt,
        );
    }
}
