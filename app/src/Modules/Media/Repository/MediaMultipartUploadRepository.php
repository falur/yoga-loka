<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<MediaMultipartUpload>
 */
final class MediaMultipartUploadRepository extends AbstractRepository
{
    public function findByMediaId(MediaId $mediaId): MediaMultipartUpload|null
    {
        return $this->findOne(['media_id' => $mediaId->value()]);
    }
}
