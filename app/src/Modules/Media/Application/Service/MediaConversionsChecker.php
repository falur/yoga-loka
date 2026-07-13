<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;

/**
 * Отвечает на вопрос «есть ли у медиа хотя бы одна готовая (Ready) конверсия любого вида
 * (image/video/audio)». Не-Ready строки (processing/processingFailed) не считаются: пригодной для
 * отдачи конверсии у них нет, поэтому потребитель (например удаление оригинала) не должен считать их
 * за «есть конверсия». Собирает знание обо всех видах конверсий в одном месте, чтобы вызывающий
 * сценарий не зависел от каждого репозитория конверсий по отдельности: при добавлении нового вида
 * конверсии правка остаётся здесь, а не в каждом обработчике.
 *
 * Ленивая проверка с ранним выходом на первой найденной конверсии: existsReadyForMediaId считает строки
 * без гидрации сущностей — до трёх count-запросов на вызов.
 */
final readonly class MediaConversionsChecker
{
    public function __construct(
        private MediaImageConversionRepository $mediaImageConversionRepository,
        private MediaVideoConversionRepository $mediaVideoConversionRepository,
        private MediaAudioConversionRepository $mediaAudioConversionRepository,
    ) {}

    public function hasAnyReadyConversion(MediaId $mediaId): bool
    {
        return $this->mediaImageConversionRepository->existsReadyForMediaId($mediaId)
            || $this->mediaVideoConversionRepository->existsReadyForMediaId($mediaId)
            || $this->mediaAudioConversionRepository->existsReadyForMediaId($mediaId);
    }
}
