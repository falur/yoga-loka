<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<Media>
 */
final class MediaRepository extends Repository
{
    public function findById(MediaId $mediaId): Media|null
    {
        return $this->findByPK($mediaId->value());
    }

    public function findByStorageKey(MediaStorageKey $storageKey): Media|null
    {
        return $this->findOne(['storage_key' => $storageKey->value()]);
    }

    public function findExpired(\DateTimeImmutable $now): MediaCollection
    {
        return new MediaCollection(
            $this->select()
                ->where('expires_at', '<=', $now)
                ->fetchAll(),
        );
    }
}
