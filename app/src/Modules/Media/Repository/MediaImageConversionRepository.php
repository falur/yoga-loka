<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

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
}
