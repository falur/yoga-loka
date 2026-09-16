<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaAudioConversionColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaImageConversionColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaMultipartUploadColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaVideoConversionColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaAudioConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaImageConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaMultipartUploadEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaVideoConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaAudioConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaImageConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMultipartUploadMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaVideoConversionMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleMediaEntity>
 */
final class CycleMediaRepository extends AbstractRepository implements MediaRepository
{
    /**
     * @param Select<CycleMediaEntity> $select
     */
    public function __construct(
        Select $select,
        private ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
        private MediaMapper $mediaMapper,
        private MediaImageConversionMapper $imageConversionMapper,
        private MediaVideoConversionMapper $videoConversionMapper,
        private MediaAudioConversionMapper $audioConversionMapper,
        private MediaMultipartUploadMapper $multipartUploadMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(MediaId $mediaId): Media|null
    {
        /** @var CycleMediaEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($mediaId->value());

        return $cycleEntity === null ? null : $this->mediaMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByIdsWithConversions(MediaId ...$mediaIds): MediaCollection
    {
        if ($mediaIds === []) {
            return new MediaCollection();
        }

        $mediaCollection = new MediaCollection();

        /** @var iterable<CycleMediaEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(MediaColumns::ID, 'in', new Parameter(\array_map(
                static fn(MediaId $mediaId): string => $mediaId->value(),
                $mediaIds,
            )))
            ->load('imageConversions')
            ->load('videoConversions')
            ->load('audioConversions')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $mediaCollection->push($this->mediaMapper->toDomainWithConversions($cycleEntity));
        }

        return $mediaCollection;
    }

    #[\Override]
    public function findByStorageKey(MediaStorageKey $storageKey): Media|null
    {
        /** @var CycleMediaEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([MediaColumns::STORAGE_KEY => $storageKey->value()]);

        return $cycleEntity === null ? null : $this->mediaMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findExpired(\DateTimeImmutable $now): MediaCollection
    {
        $mediaCollection = new MediaCollection();

        /** @var iterable<CycleMediaEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(MediaColumns::EXPIRES_AT, '<=', $now)
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $mediaCollection->push($this->mediaMapper->toDomain($cycleEntity));
        }

        return $mediaCollection;
    }

    #[\Override]
    public function findImageConversionsByMediaId(MediaId $mediaId): MediaImageConversionCollection
    {
        $imageConversions = new MediaImageConversionCollection();

        /** @var iterable<CycleMediaImageConversionEntity> $cycleEntities */
        $cycleEntities = $this->imageConversionSelect()
            ->where(MediaImageConversionColumns::MEDIA_ID, $mediaId->value())
            ->orderBy(expression: MediaImageConversionColumns::ID, direction: 'ASC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $imageConversions->push($this->imageConversionMapper->toDomain($cycleEntity));
        }

        return $imageConversions;
    }

    #[\Override]
    public function findVideoConversionsByMediaId(MediaId $mediaId): MediaVideoConversionCollection
    {
        $videoConversions = new MediaVideoConversionCollection();

        /** @var iterable<CycleMediaVideoConversionEntity> $cycleEntities */
        $cycleEntities = $this->videoConversionSelect()
            ->where(MediaVideoConversionColumns::MEDIA_ID, $mediaId->value())
            ->orderBy(expression: MediaVideoConversionColumns::ID, direction: 'ASC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $videoConversions->push($this->videoConversionMapper->toDomain($cycleEntity));
        }

        return $videoConversions;
    }

    #[\Override]
    public function findAudioConversionsByMediaId(MediaId $mediaId): MediaAudioConversionCollection
    {
        $audioConversions = new MediaAudioConversionCollection();

        /** @var iterable<CycleMediaAudioConversionEntity> $cycleEntities */
        $cycleEntities = $this->audioConversionSelect()
            ->where(MediaAudioConversionColumns::MEDIA_ID, $mediaId->value())
            ->orderBy(expression: MediaAudioConversionColumns::ID, direction: 'ASC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $audioConversions->push($this->audioConversionMapper->toDomain($cycleEntity));
        }

        return $audioConversions;
    }

    #[\Override]
    public function hasReadyImageConversion(MediaId $mediaId): bool
    {
        return $this->imageConversionSelect()
            ->where(MediaImageConversionColumns::MEDIA_ID, $mediaId->value())
            ->where(MediaImageConversionColumns::STATUS, MediaConversionStatus::Ready->value)
            ->count() > 0;
    }

    #[\Override]
    public function hasReadyVideoConversion(MediaId $mediaId): bool
    {
        return $this->videoConversionSelect()
            ->where(MediaVideoConversionColumns::MEDIA_ID, $mediaId->value())
            ->where(MediaVideoConversionColumns::STATUS, MediaConversionStatus::Ready->value)
            ->count() > 0;
    }

    #[\Override]
    public function hasReadyAudioConversion(MediaId $mediaId): bool
    {
        return $this->audioConversionSelect()
            ->where(MediaAudioConversionColumns::MEDIA_ID, $mediaId->value())
            ->where(MediaAudioConversionColumns::STATUS, MediaConversionStatus::Ready->value)
            ->count() > 0;
    }

    #[\Override]
    public function findMultipartUploadByMediaId(MediaId $mediaId): MediaMultipartUpload|null
    {
        /** @var CycleMediaMultipartUploadEntity|null $cycleEntity */
        $cycleEntity = $this->multipartUploadSelect()
            ->where(MediaMultipartUploadColumns::MEDIA_ID, $mediaId->value())
            ->fetchOne();

        return $cycleEntity === null ? null : $this->multipartUploadMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function save(Media $media): void
    {
        /** @var CycleMediaEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($media->id->value());

        $this->entityManager
            ->persist($this->mediaMapper->toCycleEntity(media: $media, cycleEntity: $cycleEntity))
            ->run();
    }

    #[\Override]
    public function delete(Media $media): void
    {
        /** @var CycleMediaEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($media->id->value());

        if ($cycleEntity === null) {
            return;
        }

        $this->entityManager
            ->delete($cycleEntity)
            ->run();
    }

    #[\Override]
    public function saveAll(MediaCollection $mediaCollection): void
    {
        foreach ($mediaCollection as $media) {
            /** @var CycleMediaEntity|null $cycleEntity */
            $cycleEntity = $this->findByPK($media->id->value());

            $this->entityManager->persist($this->mediaMapper->toCycleEntity(media: $media, cycleEntity: $cycleEntity));
        }

        $this->entityManager->run();
    }

    #[\Override]
    public function saveWithMultipartUpload(Media $media, MediaMultipartUpload $multipartUpload): void
    {
        /** @var CycleMediaEntity|null $cycleMediaEntity */
        $cycleMediaEntity = $this->findByPK($media->id->value());

        /** @var CycleMediaMultipartUploadEntity|null $cycleMultipartUploadEntity */
        $cycleMultipartUploadEntity = $this->multipartUploadSelect()
            ->where(MediaMultipartUploadColumns::ID, $multipartUpload->id->value())
            ->fetchOne();

        $this->entityManager
            ->persist($this->mediaMapper->toCycleEntity(media: $media, cycleEntity: $cycleMediaEntity))
            ->persist($this->multipartUploadMapper->toCycleEntity(
                multipartUpload: $multipartUpload,
                cycleEntity: $cycleMultipartUploadEntity,
            ))
            ->run();
    }

    #[\Override]
    public function saveWithConversions(
        Media $media,
        MediaImageConversionCollection $imageConversions,
        MediaVideoConversionCollection $videoConversions,
        MediaAudioConversionCollection $audioConversions,
    ): void {
        /** @var CycleMediaEntity|null $cycleMediaEntity */
        $cycleMediaEntity = $this->findByPK($media->id->value());

        $this->entityManager->persist($this->mediaMapper->toCycleEntity(media: $media, cycleEntity: $cycleMediaEntity));

        // Конверсии здесь всегда свежесозданы ProcessMediaHandler-ом (собственный id, ещё нет
        // строки в базе) — отдельного findOne() перед persist() не нужно, как и для RolePermission
        // в Access (см. AccessRepositoryTest::persistRolePermission()).
        foreach ($imageConversions as $imageConversion) {
            $this->entityManager->persist($this->imageConversionMapper->toCycleEntity($imageConversion));
        }

        foreach ($videoConversions as $videoConversion) {
            $this->entityManager->persist($this->videoConversionMapper->toCycleEntity($videoConversion));
        }

        foreach ($audioConversions as $audioConversion) {
            $this->entityManager->persist($this->audioConversionMapper->toCycleEntity($audioConversion));
        }

        $this->entityManager->run();
    }

    /**
     * Выборка по таблице конверсий изображения. Собственного репозитория у конверсии нет, поэтому
     * запрос строится тем же общим примитивом, что и запрос корня.
     *
     * @return WhenSelect<CycleMediaImageConversionEntity>
     */
    private function imageConversionSelect(): WhenSelect
    {
        return $this->conversionSelect(CycleMediaImageConversionEntity::class);
    }

    /**
     * @return WhenSelect<CycleMediaVideoConversionEntity>
     */
    private function videoConversionSelect(): WhenSelect
    {
        return $this->conversionSelect(CycleMediaVideoConversionEntity::class);
    }

    /**
     * @return WhenSelect<CycleMediaAudioConversionEntity>
     */
    private function audioConversionSelect(): WhenSelect
    {
        return $this->conversionSelect(CycleMediaAudioConversionEntity::class);
    }

    /**
     * @return WhenSelect<CycleMediaMultipartUploadEntity>
     */
    private function multipartUploadSelect(): WhenSelect
    {
        /** @var WhenSelect<CycleMediaMultipartUploadEntity> $select */
        $select = new WhenSelect(orm: $this->orm, role: CycleMediaMultipartUploadEntity::class);
        $select->scope($this->orm->getSource(CycleMediaMultipartUploadEntity::class)->getScope());

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
