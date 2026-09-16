<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Контракт серверных файловых операций модуля Media поверх S3-совместимого хранилища.
 *
 * Реальное имя бакета и prefix реализация резолвит из StorageConfig по алиасу MediaStorage,
 * а не из значения enum напрямую. Срок presigned-ссылок этот контракт не выбирает — presign-методы
 * принимают готовый expiresAt: для загрузки его задаёт потребитель через MediaUploadSpec, для
 * скачивания — MediaUrlService (значение по умолчанию реализация читает из MediaConfig, вызывающий
 * может переопределить).
 */
interface MediaFileServiceContract
{
    /**
     * Presigned PUT-ссылка для прямой одиночной загрузки клиентом.
     */
    public function presignPut(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        \DateTimeImmutable $expiresAt,
    ): string;

    /**
     * Инициирует multipart-загрузку и возвращает её uploadId.
     */
    public function createMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
    ): MediaMultipartUploadIdValue;

    /**
     * Presigned-ссылки на загрузку каждой части (1..partsCount).
     */
    public function presignUploadParts(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartsCount $partsCount,
        \DateTimeImmutable $expiresAt,
    ): MediaPresignedPartCollection;

    /**
     * Собирает multipart-объект из загруженных частей.
     *
     * На повторе после частичного сбоя S3 может вернуть NoSuchUpload — реализация трактует
     * это как успех, если headObject подтверждает собранный объект.
     */
    public function completeMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartCollection $parts,
    ): void;

    /**
     * Отменяет незавершённую multipart-загрузку. 404/NoSuchUpload игнорируется.
     */
    public function abortMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
    ): void;

    /**
     * Метаданные объекта. null — объекта нет.
     */
    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null;

    /**
     * Содержимое объекта (для чтения оригинала перед конверсией).
     */
    public function getObjectContents(MediaStorage $storage, MediaPath $path): string;

    /**
     * Скачивает объект в локальный временный файл и возвращает его путь. Стримовое чтение без
     * полного буфера в памяти (для ffmpeg-обработки видео/аудио). Владелец файла — вызыватель:
     * он обязан удалить его после использования (процессор делает это в finally).
     */
    public function downloadToFile(MediaStorage $storage, MediaPath $path): string;

    /**
     * Заливает локальный файл (результат ffmpeg-обработки) в хранилище стримом, без полного
     * буфера в памяти. Локальный файл не удаляется — его владелец вызыватель.
     */
    public function uploadFromFile(
        MediaStorage $storage,
        MediaPath $path,
        string $localFile,
        MediaMimeType $mimeType,
    ): void;

    /**
     * Заливает объект (результат конверсии) в хранилище.
     */
    public function putObject(
        MediaStorage $storage,
        MediaPath $path,
        string $contents,
        MediaMimeType $mimeType,
    ): void;

    /**
     * Копирует объект между хранилищами/путями (перекладка оригинала upload -> target).
     */
    public function copyObject(
        MediaStorage $fromStorage,
        MediaPath $fromPath,
        MediaStorage $toStorage,
        MediaPath $toPath,
    ): void;

    /**
     * Удаляет объект. 404 игнорируется (идемпотентное удаление).
     */
    public function deleteObject(MediaStorage $storage, MediaPath $path): void;

    /**
     * Presigned GET-ссылка с TTL (для private-медиа).
     */
    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string;

    /**
     * Прямой публичный URL (для public-медиа, бакет с anonymous-read policy).
     */
    public function publicUrl(MediaStorage $storage, MediaPath $path): string;
}
