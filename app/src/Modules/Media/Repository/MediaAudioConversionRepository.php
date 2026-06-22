<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

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
}
