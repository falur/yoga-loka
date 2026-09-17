<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Storage;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaObjectHead;
use App\Modules\Media\Application\Contract\MediaPresignedPart;
use App\Modules\Media\Application\Contract\MediaPresignedPartCollection;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Infrastructure\Storage\MediaStorageNotConfiguredException;
use App\Modules\Media\Infrastructure\Spiral\Configuration\MediaStorageConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageBucketConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageConfig;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Psr\Http\Message\StreamInterface;

final readonly class S3MediaFileService implements MediaFileServiceContract
{
    /**
     * @var list<string>
     */
    private const array TRANSIENT_AWS_ERROR_CODES = [
        'Throttling',
        'ThrottlingException',
        'RequestThrottled',
        'RequestTimeout',
        'SlowDown',
    ];

    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';

    public function __construct(
        private StorageConfig $storageConfig,
        private MediaStorageConfig $mediaStorageConfig,
        private S3ClientProvider $clientProvider,
    ) {}

    #[\Override]
    public function presignPut(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        \DateTimeImmutable $expiresAt,
    ): string {
        $client = $this->client($storage);
        $command = $client->getCommand(name: 'PutObject', args: [
            'Bucket' => $this->bucketName($storage),
            'Key' => $this->objectKey(storage: $storage, path: $path),
            'ContentType' => $mimeType->value(),
        ]);

        return (string) $client->createPresignedRequest(command: $command, expires: $expiresAt)->getUri();
    }

    #[\Override]
    public function createMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
    ): MediaMultipartUploadIdValue {
        try {
            $uploadId = $this->client($storage)->createMultipartUpload([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
                'ContentType' => $mimeType->value(),
            ])['UploadId'] ?? null;
        } catch (AwsException $exception) {
            throw $this->awsFailure($exception);
        }

        if (!\is_string($uploadId) || $uploadId === '') {
            throw MediaFileServiceFailedException::permanent('S3 не вернул UploadId при инициации multipart-загрузки.');
        }

        return MediaMultipartUploadIdValue::fromString($uploadId);
    }

    #[\Override]
    public function presignUploadParts(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartsCount $partsCount,
        \DateTimeImmutable $expiresAt,
    ): MediaPresignedPartCollection {
        $client = $this->client($storage);
        $bucket = $this->bucketName($storage);
        $key = $this->objectKey(storage: $storage, path: $path);

        $parts = [];
        for ($partNumber = 1; $partNumber <= $partsCount->value(); $partNumber++) {
            $command = $client->getCommand(name: 'UploadPart', args: [
                'Bucket' => $bucket,
                'Key' => $key,
                'UploadId' => $uploadId->value(),
                'PartNumber' => $partNumber,
            ]);

            $parts[] = new MediaPresignedPart(
                partNumber: $partNumber,
                url: (string) $client->createPresignedRequest(command: $command, expires: $expiresAt)->getUri(),
            );
        }

        return new MediaPresignedPartCollection($parts);
    }

    #[\Override]
    public function completeMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartCollection $parts,
    ): void {
        // \array_values() задаёт список: SDK ожидает part-ы строго с последовательными ключами.
        $sdkParts = \array_values($parts
            ->toBase()
            ->map(static fn(MediaMultipartPart $part): array => [
                'PartNumber' => $part->partNumber->value(),
                'ETag' => $part->eTag->value(),
            ])
            ->all());

        try {
            $this->client($storage)->completeMultipartUpload([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
                'UploadId' => $uploadId->value(),
                'MultipartUpload' => ['Parts' => $sdkParts],
            ]);
        } catch (S3Exception $exception) {
            // Идемпотентный повтор: части уже собраны в объект, S3 потерял запись upload.
            // Считаем успехом только если собранный объект реально существует.
            if ($exception->getAwsErrorCode() === 'NoSuchUpload' && $this->headObject(storage: $storage, path: $path) !== null) {
                return;
            }

            throw $this->awsFailure($exception);
        }
    }

    #[\Override]
    public function abortMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
    ): void {
        try {
            $this->client($storage)->abortMultipartUpload([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
                'UploadId' => $uploadId->value(),
            ]);
        } catch (S3Exception $exception) {
            if ($this->isNotFound($exception)) {
                return;
            }

            throw $this->awsFailure($exception);
        }
    }

    #[\Override]
    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null
    {
        try {
            $contentLength = $this->client($storage)->headObject([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
            ])['ContentLength'] ?? null;
        } catch (S3Exception $exception) {
            if ($this->isNotFound($exception)) {
                return null;
            }

            throw $this->awsFailure($exception);
        }

        if (!\is_int($contentLength) && !(\is_string($contentLength) && \ctype_digit($contentLength))) {
            throw MediaFileServiceFailedException::permanent('S3 не вернул корректный ContentLength для объекта.');
        }

        return new MediaObjectHead(contentLength: MediaFileSize::fromInt((int) $contentLength));
    }

    #[\Override]
    public function getObjectContents(MediaStorage $storage, MediaPath $path): string
    {
        try {
            $body = $this->client($storage)->getObject([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
            ])['Body'] ?? null;
        } catch (AwsException $exception) {
            throw $this->awsFailure($exception);
        }

        if (!$body instanceof StreamInterface) {
            throw MediaFileServiceFailedException::permanent('S3 вернул объект без читаемого тела.');
        }

        return (string) $body;
    }

    #[\Override]
    public function downloadToFile(MediaStorage $storage, MediaPath $path): string
    {
        // Уникальный путь без предварительного создания файла: 'SaveAs' создаёт его сам,
        // стримя тело из S3 на диск без полного буфера в памяти. Владелец файла — вызыватель.
        $localFile = \sprintf('%s/media_download_%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(16)));

        try {
            $this->client($storage)->getObject([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
                'SaveAs' => $localFile,
            ]);
        } catch (AwsException $exception) {
            // 'SaveAs' мог записать частичный файл до обрыва. Вызыватель пути ещё не получил, очистить
            // его некому — удаляем здесь, чтобы сбой скачивания не оставлял мусор во временном каталоге.
            if (\is_file($localFile)) {
                \unlink($localFile);
            }

            throw $this->awsFailure($exception);
        }

        return $localFile;
    }

    #[\Override]
    public function uploadFromFile(
        MediaStorage $storage,
        MediaPath $path,
        string $localFile,
        MediaMimeType $mimeType,
    ): void {
        try {
            $this->client($storage)->putObject([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
                'SourceFile' => $localFile,
                'ContentType' => $mimeType->value(),
            ]);
        } catch (AwsException $exception) {
            throw $this->awsFailure($exception);
        }
    }

    #[\Override]
    public function putObject(
        MediaStorage $storage,
        MediaPath $path,
        string $contents,
        MediaMimeType $mimeType,
    ): void {
        try {
            $this->client($storage)->putObject([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
                'Body' => $contents,
                'ContentType' => $mimeType->value(),
            ]);
        } catch (AwsException $exception) {
            throw $this->awsFailure($exception);
        }
    }

    #[\Override]
    public function copyObject(
        MediaStorage $fromStorage,
        MediaPath $fromPath,
        MediaStorage $toStorage,
        MediaPath $toPath,
    ): void {
        $copySource = \sprintf('%s/%s', $this->bucketName($fromStorage), $this->objectKey(storage: $fromStorage, path: $fromPath));

        try {
            $this->client($toStorage)->copyObject([
                'Bucket' => $this->bucketName($toStorage),
                'Key' => $this->objectKey(storage: $toStorage, path: $toPath),
                'CopySource' => $copySource,
            ]);
        } catch (AwsException $exception) {
            throw $this->awsFailure($exception);
        }
    }

    #[\Override]
    public function deleteObject(MediaStorage $storage, MediaPath $path): void
    {
        try {
            $this->client($storage)->deleteObject([
                'Bucket' => $this->bucketName($storage),
                'Key' => $this->objectKey(storage: $storage, path: $path),
            ]);
        } catch (S3Exception $exception) {
            if ($this->isNotFound($exception)) {
                return;
            }

            throw $this->awsFailure($exception);
        }
    }

    #[\Override]
    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
    {
        $client = $this->client($storage);
        $command = $client->getCommand(name: 'GetObject', args: [
            'Bucket' => $this->bucketName($storage),
            'Key' => $this->objectKey(storage: $storage, path: $path),
        ]);

        return (string) $client->createPresignedRequest(command: $command, expires: $expiresAt)->getUri();
    }

    #[\Override]
    public function publicUrl(MediaStorage $storage, MediaPath $path): string
    {
        return $this->client($storage)->getObjectUrl(
            bucket: $this->bucketName($storage),
            key: $this->objectKey(storage: $storage, path: $path),
        );
    }

    private function bucketConfig(MediaStorage $storage): StorageBucketConfig
    {
        return $this->mediaStorageConfig->buckets[$storage->value]
            ?? throw MediaStorageNotConfiguredException::bucketAliasMissing($storage);
    }

    private function bucketName(MediaStorage $storage): string
    {
        return $this->bucketConfig($storage)->bucket
            ?? throw MediaStorageNotConfiguredException::bucketNameMissing($storage);
    }

    private function objectKey(MediaStorage $storage, MediaPath $path): string
    {
        $prefix = $this->bucketConfig($storage)->prefix;

        if ($prefix === null || $prefix === '') {
            return $path->value();
        }

        return \sprintf('%s/%s', \rtrim(string: $prefix, characters: '/'), $path->value());
    }

    private function client(MediaStorage $storage): S3Client
    {
        $bucketConfig = $this->bucketConfig($storage);
        $serverConfig = $this->storageConfig->servers[$bucketConfig->server]
            ?? throw MediaStorageNotConfiguredException::serverMissing(storage: $storage, server: $bucketConfig->server);

        return $this->clientProvider->forServer($serverConfig);
    }

    private function isNotFound(S3Exception $exception): bool
    {
        return $exception->getStatusCode() === 404
            || \in_array(
                needle: $exception->getAwsErrorCode(),
                haystack: ['NoSuchKey', 'NotFound', 'NoSuchUpload'],
                strict: true,
            );
    }

    /**
     * Классифицирует сырое AWS-исключение в исключение контракта с типизированным признаком
     * временной ошибки. Знание об AWS остаётся в Infrastructure; наружу уходит только контрактный
     * сигнал isTransient(). Сообщение — безопасная предопределённая строка без сырого текста AWS.
     */
    private function awsFailure(AwsException $exception): MediaFileServiceFailedException
    {
        return $this->isTransientAwsException($exception)
            ? MediaFileServiceFailedException::transient(message: self::STORAGE_FAILURE_MESSAGE, previous: $exception)
            : MediaFileServiceFailedException::permanent(message: self::STORAGE_FAILURE_MESSAGE, previous: $exception);
    }

    private function isTransientAwsException(AwsException $exception): bool
    {
        if ($exception->isConnectionError()) {
            return true;
        }

        $statusCode = $exception->getStatusCode();
        if ($statusCode !== null && $statusCode >= 500) {
            return true;
        }

        return \in_array(
            needle: $exception->getAwsErrorCode(),
            haystack: self::TRANSIENT_AWS_ERROR_CODES,
            strict: true,
        );
    }
}
