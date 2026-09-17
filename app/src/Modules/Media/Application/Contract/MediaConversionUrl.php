<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;

/**
 * URL одной конверсии медиа. `kind` — вид (image/video/audio): чем рендерить на клиенте; постер видео
 * имеет kind = image, т.к. это кадр-картинка. `type` — конкретный профиль конверсии. Вызывающий
 * выбирает нужную по виду/типу, не зная заранее, какие конверсии есть. expiresAt = null для прямого
 * публичного URL, заполнен для presigned-ссылки private-медиа.
 */
final readonly class MediaConversionUrl
{
    public function __construct(
        public MediaConversionKind $kind,
        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}
}
