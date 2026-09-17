<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Repository;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;

/**
 * Хранение медиа. Корень агрегата — Media, его внутренние сущности — конверсии изображения, видео,
 * звука и multipart-загрузка: они существуют только у своего медиа, создаются и удаляются вместе с
 * ним (связь HasMany/BelongsTo, внешний ключ ON DELETE CASCADE, отдельного создания нет), поэтому
 * своего репозитория у них нет и выборку по их таблицам ведёт этот репозиторий.
 */
interface MediaRepository
{
    public function findById(MediaId $mediaId): Media|null;

    /**
     * Набор медиа по списку id вместе со всеми конверсиями (image/video/audio), загруженными одним
     * набором запросов, чтобы построение URL не дёргало выборку конверсий по одному. Используется
     * там, где нужен полный набор ссылок сразу для нескольких медиа (например, аватары авторов на
     * странице инбокса уведомлений) без N+1.
     */
    public function findByIdsWithConversions(MediaId ...$mediaIds): MediaCollection;

    public function findByStorageKey(MediaStorageKey $storageKey): Media|null;

    public function findExpired(\DateTimeImmutable $now): MediaCollection;

    /**
     * Конверсии изображения — внутренние сущности агрегата.
     */
    public function findImageConversionsByMediaId(MediaId $mediaId): MediaImageConversionCollection;

    /**
     * Конверсии видео — внутренние сущности агрегата.
     */
    public function findVideoConversionsByMediaId(MediaId $mediaId): MediaVideoConversionCollection;

    /**
     * Конверсии звука — внутренние сущности агрегата.
     */
    public function findAudioConversionsByMediaId(MediaId $mediaId): MediaAudioConversionCollection;

    /**
     * Есть ли у медиа хоть одна готовая (Ready) image-конверсия. Не-Ready строки (processing/
     * processingFailed) не учитываются: пригодной для отдачи конверсии у них нет.
     */
    public function hasReadyImageConversion(MediaId $mediaId): bool;

    /**
     * Есть ли у медиа хоть одна готовая (Ready) video-конверсия. Не-Ready строки (processing/
     * processingFailed) не учитываются: пригодной для отдачи конверсии у них нет.
     */
    public function hasReadyVideoConversion(MediaId $mediaId): bool;

    /**
     * Есть ли у медиа хоть одна готовая (Ready) audio-конверсия. Не-Ready строки (processing/
     * processingFailed) не учитываются: пригодной для отдачи конверсии у них нет.
     */
    public function hasReadyAudioConversion(MediaId $mediaId): bool;

    /**
     * Multipart-загрузка — внутренняя сущность агрегата, не более одной активной на медиа.
     */
    public function findMultipartUploadByMediaId(MediaId $mediaId): MediaMultipartUpload|null;

    public function save(Media $media): void;

    public function delete(Media $media): void;

    /**
     * Сохраняет набор медиа одним прогоном EntityManager. Используется там, где сценарий обходит
     * несколько медиа и фиксирует изменения всего набора одной записью после обхода.
     */
    public function saveAll(MediaCollection $mediaCollection): void;

    /**
     * Сохраняет медиа вместе с его multipart-загрузкой одним прогоном EntityManager: multipart-
     * загрузка создаётся или изменяется только вместе со своим медиа и собственного репозитория не
     * получает.
     */
    public function saveWithMultipartUpload(Media $media, MediaMultipartUpload $multipartUpload): void;

    /**
     * Сохраняет медиа вместе с созданными при обработке конверсиями одним прогоном EntityManager:
     * конверсии создаются только вместе со своим медиа и собственного репозитория не получают.
     */
    public function saveWithConversions(
        Media $media,
        MediaImageConversionCollection $imageConversions,
        MediaVideoConversionCollection $videoConversions,
        MediaAudioConversionCollection $audioConversions,
    ): void;
}
