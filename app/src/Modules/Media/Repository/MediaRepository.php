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
    public function findById(MediaId $mediaId): ?Media
    {
        $media = $this->findByPK($mediaId->value());

        return $media instanceof Media ? $media : null;
    }

    public function findByStorageKey(MediaStorageKey $storageKey): ?Media
    {
        $media = $this->findOne(['storage_key' => $storageKey->value()]);

        return $media instanceof Media ? $media : null;
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
