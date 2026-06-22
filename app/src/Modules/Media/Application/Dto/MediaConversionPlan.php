<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * План конверсий на тип медиа: отдельный список профилей для каждого MediaType. Потребитель
 * задаёт списки на тип, валидируется только список, относящийся к типу медиа.
 *
 * Все три списка несут точные PHPDoc-типы list<...Spec> — это условие восстановления вложенного
 * DTO через ValinorOutboxMessageSerializer (план кладётся в outbox-сообщение MediaUploaded).
 */
final readonly class MediaConversionPlan
{
    /**
     * @param list<MediaImageConversionSpec> $image
     * @param list<MediaVideoConversionSpec> $video
     * @param list<MediaAudioConversionSpec> $audio
     */
    public function __construct(
        public array $image,
        public array $video,
        public array $audio,
    ) {}
}
