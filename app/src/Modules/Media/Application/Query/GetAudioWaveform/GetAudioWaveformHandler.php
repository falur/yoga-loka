<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetAudioWaveform;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\NotFoundException;

/**
 * Возвращает волну амплитуд нормализованной аудио-конверсии (числа для прогресса воспроизведения).
 * Волна не отдаётся через URL — это отдельный запрос. Медиа не финализировано (не ready и не
 * readyOriginalRemoved) / не аудио / без конверсии — 404.
 */
final readonly class GetAudioWaveformHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaAudioConversionRepository $mediaAudioConversionRepository,
    ) {}

    public function handle(GetAudioWaveformQuery $query): MediaWaveform
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        // Волна — это конверсия, поэтому переживает удаление оригинала (ready и readyOriginalRemoved).
        if (!$media->isFinalized()) {
            throw new NotFoundException('app.media.not_ready');
        }

        if ($media->type !== MediaType::Audio) {
            throw new NotFoundException('app.media.conversion_not_found');
        }

        // Каталог аудио-типов содержит ровно один профиль (NormalizedAacM4a) и на медиа не больше
        // одной такой конверсии, поэтому берём первую без фильтра по единственному типу.
        $conversion = $this->mediaAudioConversionRepository->findByMediaId($media->id)->first()
            ?? throw new NotFoundException('app.media.conversion_not_found');

        return $conversion->waveform;
    }
}
