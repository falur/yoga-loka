<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Dto;

/**
 * План конверсий на тип медиа: отдельный список профилей для каждого MediaType. Потребитель
 * задаёт списки на тип, валидируется только список, относящийся к типу медиа.
 *
 * Все три списка несут точные PHPDoc-типы list<...Dto> — это условие восстановления вложенного
 * DTO сериализатором пакета outbox (план кладётся в событие MediaUploadedEvent).
 */
final readonly class MediaConversionPlanDto
{
    /**
     * @param list<MediaImageConversionSpecDto> $image
     * @param list<MediaVideoConversionSpecDto> $video
     * @param list<MediaAudioConversionSpecDto> $audio
     */
    public function __construct(
        public array $image,
        public array $video,
        public array $audio,
    ) {}
}
