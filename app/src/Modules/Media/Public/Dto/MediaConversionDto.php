<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Dto;

use App\Modules\Media\Public\Enum\MediaAudioConversionType;
use App\Modules\Media\Public\Enum\MediaConversionKind;
use App\Modules\Media\Public\Enum\MediaImageConversionType;
use App\Modules\Media\Public\Enum\MediaVideoConversionType;

/**
 * Одна конверсия медиа в межмодульном ответе: вид (image/video/audio) — чем рендерить на клиенте, и
 * тип-профиль конверсии. Постер видео имеет вид image, потому что это кадр-картинка. Потребитель
 * выбирает нужную конверсию по виду или типу, не зная заранее, какие конверсии есть.
 * expiresAt = null для прямой публичной ссылки, заполнен для presigned-ссылки private-медиа.
 */
final readonly class MediaConversionDto
{
    public function __construct(
        public MediaConversionKind $kind,
        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}
}
