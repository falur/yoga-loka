<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetAudioWaveform;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Exception\MediaConversionNotFoundException;
use App\Modules\Media\Domain\Exception\MediaNotFinalizedException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;

/**
 * Возвращает волну амплитуд нормализованной аудио-конверсии (числа для прогресса воспроизведения).
 * Волна не отдаётся через URL — это отдельный запрос. Медиа не финализировано (не ready и не
 * readyOriginalRemoved) / не аудио / без конверсии — 404.
 */
final readonly class GetAudioWaveformHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {}

    public function handle(GetAudioWaveformQuery $query): MediaWaveform
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new MediaNotFoundException();

        // Волна — это конверсия, поэтому переживает удаление оригинала (ready и readyOriginalRemoved).
        if (!$media->isFinalized()) {
            throw new MediaNotFinalizedException();
        }

        if ($media->type !== MediaType::Audio) {
            throw new MediaConversionNotFoundException();
        }

        // Каталог аудио-типов содержит ровно один профиль (NormalizedAacM4a) и на медиа не больше
        // одной такой конверсии, поэтому берём первую без фильтра по единственному типу.
        $conversion = $this->mediaRepository->findAudioConversionsByMediaId($media->id)->first()
            ?? throw new MediaConversionNotFoundException();

        return $conversion->waveform;
    }
}
