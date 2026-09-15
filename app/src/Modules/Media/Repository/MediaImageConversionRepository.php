<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<MediaImageConversion>
 */
final class MediaImageConversionRepository extends AbstractRepository
{
    public function findByMediaId(MediaId $mediaId): MediaImageConversionCollection
    {
        return new MediaImageConversionCollection(
            $this->select()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    /**
     * Есть ли у медиа хоть одна готовая (Ready) image-конверсия. Не-Ready строки (processing/
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
