<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;

/**
 * Отвечает на вопрос «есть ли у медиа хотя бы одна готовая (Ready) конверсия любого вида
 * (image/video/audio)». Не-Ready строки (processing/processingFailed) не считаются: пригодной для
 * отдачи конверсии у них нет, поэтому потребитель (например удаление оригинала) не должен считать их
 * за «есть конверсия». Собирает знание обо всех видах конверсий в одном месте, чтобы вызывающий
 * сценарий не зависел от каждого репозитория конверсий по отдельности: при добавлении нового вида
 * конверсии правка остаётся здесь, а не в каждом обработчике.
 *
 * Ленивая проверка с ранним выходом на первой найденной конверсии: hasReady*Conversion считает строки
 * без гидрации сущностей — до трёх count-запросов на вызов.
 */
final readonly class MediaConversionsChecker
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {}

    public function hasAnyReadyConversion(MediaId $mediaId): bool
    {
        return $this->mediaRepository->hasReadyImageConversion($mediaId)
            || $this->mediaRepository->hasReadyVideoConversion($mediaId)
            || $this->mediaRepository->hasReadyAudioConversion($mediaId);
    }
}
