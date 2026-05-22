<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\ValueObject\MediaId;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<MediaVideoConversion>
 */
final class MediaVideoConversionRepository extends Repository
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
}
