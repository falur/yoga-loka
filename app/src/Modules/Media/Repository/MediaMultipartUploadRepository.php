<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\ValueObject\MediaId;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<MediaMultipartUpload>
 */
final class MediaMultipartUploadRepository extends Repository
{
    public function findByMediaId(MediaId $mediaId): ?MediaMultipartUpload
    {
        $multipartUpload = $this->findOne(['media_id' => $mediaId->value()]);

        return $multipartUpload instanceof MediaMultipartUpload ? $multipartUpload : null;
    }
}
