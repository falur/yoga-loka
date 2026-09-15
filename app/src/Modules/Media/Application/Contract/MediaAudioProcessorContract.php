<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Public\Dto\MediaAudioConversionSpecDto;
use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Контракт обработки аудио: транскод оригинала в m4a/AAC и извлечение волны амплитуд.
 *
 * Реализация владеет временными файлами: скачивает оригинал в локальный файл, гоняет ffmpeg
 * (probe/транскод/волна), загружает результат в целевое хранилище и удаляет все временные файлы
 * в finally при любом исходе. Целевой путь (normalizedPath) строит Application-handler доменной
 * фабрикой и передаёт готовым — Infrastructure доменные пути не строит.
 */
interface MediaAudioProcessorContract
{
    public function process(
        MediaStorage $sourceStorage,
        MediaPath $sourcePath,
        MediaAudioConversionSpecDto $spec,
        MediaStorage $targetStorage,
        MediaPath $normalizedPath,
    ): MediaAudioProcessingResult;
}
