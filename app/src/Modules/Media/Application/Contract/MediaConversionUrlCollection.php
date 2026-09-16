<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, MediaConversionUrl>
 */
final class MediaConversionUrlCollection extends TypedCollection
{
    /**
     * Конверсия запрошенного типа или null, если её нет. Позволяет вызывающему выбрать нужный
     * профиль по типу, не зная заранее, какие конверсии присутствуют, и без отдельного запроса.
     */
    public function ofType(
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
    ): MediaConversionUrl|null {
        return $this->first(static fn(MediaConversionUrl $conversion): bool => $conversion->type === $type);
    }
}
