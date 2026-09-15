<?php

declare(strict_types=1);

namespace App\Modules\Media\Repository;

use App\Modules\Media\Domain\Collection\MediaCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

/**
 * @extends AbstractRepository<Media>
 */
final class MediaRepository extends AbstractRepository
{
    public function findById(MediaId $mediaId): Media|null
    {
        return $this->findByPK($mediaId->value());
    }

    /**
     * Набор медиа по списку id вместе со всеми конверсиями (image/video/audio), загруженными одним
     * набором запросов, чтобы построение URL не дёргало репозитории конверсий по одному. Используется
     * там, где нужен полный набор ссылок сразу для нескольких медиа (например, аватары авторов на
     * странице инбокса уведомлений) без N+1.
     */
    public function findByIdsWithConversions(MediaId ...$mediaIds): MediaCollection
    {
        if ($mediaIds === []) {
            return new MediaCollection();
        }

        return new MediaCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(MediaId $mediaId): string => $mediaId->value(),
                    $mediaIds,
                )))
                ->load('imageConversions')
                ->load('videoConversions')
                ->load('audioConversions')
                ->fetchAll(),
        );
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
