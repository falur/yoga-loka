<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<Media>
 */
final class CycleMediaRepository extends AbstractRepository implements MediaRepository
{
    /**
     * @param Select<Media> $select
     */
    public function __construct(
        Select $select,
        private ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(MediaId $mediaId): Media|null
    {
        return $this->findByPK($mediaId->value());
    }

    #[\Override]
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

    #[\Override]
    public function findByStorageKey(MediaStorageKey $storageKey): Media|null
    {
        return $this->findOne(['storage_key' => $storageKey->value()]);
    }

    #[\Override]
    public function findExpired(\DateTimeImmutable $now): MediaCollection
    {
        return new MediaCollection(
            $this->select()
                ->where('expires_at', '<=', $now)
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findImageConversionsByMediaId(MediaId $mediaId): MediaImageConversionCollection
    {
        return new MediaImageConversionCollection(
            $this->imageConversionSelect()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findVideoConversionsByMediaId(MediaId $mediaId): MediaVideoConversionCollection
    {
        return new MediaVideoConversionCollection(
            $this->videoConversionSelect()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findAudioConversionsByMediaId(MediaId $mediaId): MediaAudioConversionCollection
    {
        return new MediaAudioConversionCollection(
            $this->audioConversionSelect()
                ->where('media_id', $mediaId->value())
                ->orderBy(expression: 'id', direction: 'ASC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function hasReadyImageConversion(MediaId $mediaId): bool
    {
        return $this->imageConversionSelect()
            ->where('media_id', $mediaId->value())
            ->where('status', MediaConversionStatus::Ready->value)
            ->count() > 0;
    }

    #[\Override]
    public function hasReadyVideoConversion(MediaId $mediaId): bool
    {
        return $this->videoConversionSelect()
            ->where('media_id', $mediaId->value())
            ->where('status', MediaConversionStatus::Ready->value)
            ->count() > 0;
    }

    #[\Override]
    public function hasReadyAudioConversion(MediaId $mediaId): bool
    {
        return $this->audioConversionSelect()
            ->where('media_id', $mediaId->value())
            ->where('status', MediaConversionStatus::Ready->value)
            ->count() > 0;
    }

    #[\Override]
    public function findMultipartUploadByMediaId(MediaId $mediaId): MediaMultipartUpload|null
    {
        /** @var MediaMultipartUpload|null $multipartUpload */
        $multipartUpload = $this->multipartUploadSelect()
            ->where('media_id', $mediaId->value())
            ->fetchOne();

        return $multipartUpload;
    }

    #[\Override]
    public function save(Media $media): void
    {
        $this->entityManager
            ->persist($media)
            ->run();
    }

    #[\Override]
    public function delete(Media $media): void
    {
        $this->entityManager
            ->delete($media)
            ->run();
    }

    #[\Override]
    public function saveAll(MediaCollection $mediaCollection): void
    {
        foreach ($mediaCollection as $media) {
            $this->entityManager->persist($media);
        }

        $this->entityManager->run();
    }

    #[\Override]
    public function saveWithMultipartUpload(Media $media, MediaMultipartUpload $multipartUpload): void
    {
        $this->entityManager
            ->persist($media)
            ->persist($multipartUpload)
            ->run();
    }

    #[\Override]
    public function saveWithConversions(
        Media $media,
        MediaImageConversionCollection $imageConversions,
        MediaVideoConversionCollection $videoConversions,
        MediaAudioConversionCollection $audioConversions,
    ): void {
        $this->entityManager->persist($media);

        foreach ($imageConversions as $imageConversion) {
            $this->entityManager->persist($imageConversion);
        }

        foreach ($videoConversions as $videoConversion) {
            $this->entityManager->persist($videoConversion);
        }

        foreach ($audioConversions as $audioConversion) {
            $this->entityManager->persist($audioConversion);
        }

        $this->entityManager->run();
    }

    /**
     * Выборка по таблице конверсий изображения. Собственного репозитория у конверсии нет, поэтому
     * запрос строится тем же общим примитивом, что и запрос корня.
     *
     * @return WhenSelect<MediaImageConversion>
     */
    private function imageConversionSelect(): WhenSelect
    {
        return $this->conversionSelect(MediaImageConversion::class);
    }

    /**
     * @return WhenSelect<MediaVideoConversion>
     */
    private function videoConversionSelect(): WhenSelect
    {
        return $this->conversionSelect(MediaVideoConversion::class);
    }

    /**
     * @return WhenSelect<MediaAudioConversion>
     */
    private function audioConversionSelect(): WhenSelect
    {
        return $this->conversionSelect(MediaAudioConversion::class);
    }

    /**
     * @return WhenSelect<MediaMultipartUpload>
     */
    private function multipartUploadSelect(): WhenSelect
    {
        /** @var WhenSelect<MediaMultipartUpload> $select */
        $select = new WhenSelect(orm: $this->orm, role: MediaMultipartUpload::class);
        $select->scope($this->orm->getSource(MediaMultipartUpload::class)->getScope());

        return $select;
    }

    /**
     * @template TConversion of object
     *
     * @param class-string<TConversion> $role
     *
     * @return WhenSelect<TConversion>
     */
    private function conversionSelect(string $role): WhenSelect
    {
        /** @var WhenSelect<TConversion> $select */
        $select = new WhenSelect(orm: $this->orm, role: $role);
        $select->scope($this->orm->getSource($role)->getScope());

        return $select;
    }
}
