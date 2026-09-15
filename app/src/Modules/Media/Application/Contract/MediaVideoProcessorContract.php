<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Public\Dto\MediaVideoConversionSpecDto;
use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Контракт обработки видео: транскод оригинала в mp4/H.264+AAC и кадр-постер.
 *
 * Реализация владеет временными файлами: скачивает оригинал в локальный файл, гоняет ffmpeg
 * (probe/транскод/постер/волна), загружает результаты в целевое хранилище и удаляет все
 * временные файлы в finally при любом исходе. Целевые пути (normalizedPath, posterPath) строит
 * Application-handler доменными фабриками и передаёт готовыми — Infrastructure доменные пути не строит.
 */
interface MediaVideoProcessorContract
{
    public function process(
        MediaStorage $sourceStorage,
        MediaPath $sourcePath,
        MediaVideoConversionSpecDto $spec,
        MediaStorage $targetStorage,
        MediaPath $normalizedPath,
        MediaPath $posterPath,
    ): MediaVideoProcessingResult;
}
