<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<MediaVideoConversion>
 */
final class MediaVideoConversionRepository extends AbstractRepository
{
    public function findByMediaId(MediaId $mediaId): MediaVideoConversionCollection
    {
        return new MediaVideoConversionCollection(
            $this->select()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    /**
     * Есть ли у медиа хоть одна готовая (Ready) video-конверсия. Не-Ready строки (processing/
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
