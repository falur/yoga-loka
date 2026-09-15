<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<MediaAudioConversion>
 */
final class MediaAudioConversionRepository extends AbstractRepository
{
    public function findByMediaId(MediaId $mediaId): MediaAudioConversionCollection
    {
        return new MediaAudioConversionCollection(
            $this->select()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    /**
     * Есть ли у медиа хоть одна готовая (Ready) audio-конверсия. Не-Ready строки (processing/
     * processingFailed) не учитываются: пригодной для отдачи конверсии у них нет. Считает строки без
     * гидрации сущностей — для булевой проверки не нужно поднимать конверсии целиком.
     */
    public function existsReadyForMediaId(MediaId $mediaId): bool
    {
        return $this->select()
            ->where('media_id', $mediaId->value())
            ->where('status', MediaConversionStatus::Ready->value)
            ->count() > 0;
    }
}
