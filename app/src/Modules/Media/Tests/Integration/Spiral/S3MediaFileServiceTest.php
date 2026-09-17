<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Infrastructure\Storage\ConfiguredS3ClientProvider;
use App\Modules\Media\Infrastructure\Storage\S3MediaFileService;
use App\Modules\Media\Infrastructure\Spiral\Configuration\MediaStorageConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageConfig;
use GuzzleHttp\Client;
use Tests\TestCase;

final class S3MediaFileServiceTest extends TestCase
{
    private const string MIME = 'image/jpeg';

    /**
     * @var list<array{storage: MediaStorage, path: MediaPath}>
     */
    private array $createdObjects = [];

    public function testPresignPutThenHeadObjectReportsContentLength(): void
    {
        $fileService = $this->fileService();
        $path = $this->uploadPath();
        $bytes = \random_bytes(2048);
        $this->track(MediaStorage::Upload, $path);

        $putUrl = $fileService->presignPut(
            storage: MediaStorage::Upload,
            path: $path,
            mimeType: MediaMimeType::fromString(self::MIME),
            expiresAt: $this->expiresAt(),
        );
        $putResponse = $this->httpClient()->put($putUrl, [
            'body' => $bytes,
            'headers' => ['Content-Type' => self::MIME],
        ]);

        $head = $fileService->headObject(MediaStorage::Upload, $path);

        self::assertSame(200, $putResponse->getStatusCode());
        self::assertNotNull($head);
        self::assertSame(2048, $head->contentLength->value());
    }

    public function testMultipartUploadCreatePresignCompleteAssemblesObject(): void
    {
        $fileService = $this->fileService();
        $path = $this->uploadPath();
        $bytes = \random_bytes(4096);
        $this->track(MediaStorage::Upload, $path);

        $uploadId = $fileService->createMultipartUpload(
            storage: MediaStorage::Upload,
            path: $path,
            mimeType: MediaMimeType::fromString(self::MIME),
        );
        $presignedParts = $fileService->presignUploadParts(
            storage: MediaStorage::Upload,
            path: $path,
            uploadId: $uploadId,
            partsCount: MediaMultipartPartsCount::fromInt(1),
            expiresAt: $this->expiresAt(),
        );

        self::assertCount(1, $presignedParts);
        $presignedPart = $presignedParts->first();
        $partResponse = $this->httpClient()->put($presignedPart->url, ['body' => $bytes]);
        $eTag = \trim($partResponse->getHeaderLine('ETag'));

        $fileService->completeMultipartUpload(
            storage: MediaStorage::Upload,
            path: $path,
            uploadId: $uploadId,
            parts: new MediaMultipartPartCollection([
                MediaMultipartPart::create(
                    partNumber: MediaMultipartPartNumber::fromInt($presignedPart->partNumber),
                    eTag: MediaMultipartPartETag::fromString($eTag),
                ),
            ]),
        );

        $head = $fileService->headObject(MediaStorage::Upload, $path);
        self::assertNotNull($head);
        self::assertSame(4096, $head->contentLength->value());
    }

    public function testCopyObjectToPublicBucketIsAnonymouslyReadable(): void
    {
        $fileService = $this->fileService();
        $uploadPath = $this->uploadPath();
        $publicPath = $this->imagesPath();
        $bytes = \random_bytes(1024);
        $this->track(MediaStorage::Upload, $uploadPath);
        $this->track(MediaStorage::Public, $publicPath);

        $fileService->putObject(
            storage: MediaStorage::Upload,
            path: $uploadPath,
            contents: $bytes,
            mimeType: MediaMimeType::fromString(self::MIME),
        );
        $fileService->copyObject(
            fromStorage: MediaStorage::Upload,
            fromPath: $uploadPath,
            toStorage: MediaStorage::Public,
            toPath: $publicPath,
        );

        $publicUrl = $fileService->publicUrl(MediaStorage::Public, $publicPath);
        $publicResponse = $this->httpClient()->get($publicUrl);

        self::assertStringContainsString('media-public', $publicUrl);
        self::assertStringContainsString('test/', $publicUrl);
        self::assertSame(200, $publicResponse->getStatusCode());
        self::assertSame($bytes, (string) $publicResponse->getBody());
    }

    public function testPresignGetReturnsWorkingUrlForPrivateBucket(): void
    {
        $fileService = $this->fileService();
        $path = $this->imagesPath();
        $bytes = \random_bytes(1024);
        $this->track(MediaStorage::Private, $path);

        $fileService->putObject(
            storage: MediaStorage::Private,
            path: $path,
            contents: $bytes,
            mimeType: MediaMimeType::fromString(self::MIME),
        );

        $presignedUrl = $fileService->presignGet(MediaStorage::Private, $path, $this->expiresAt());
        $response = $this->httpClient()->get($presignedUrl);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($bytes, (string) $response->getBody());
    }

    public function testDeleteObjectRemovesObjectAndIgnoresMissing(): void
    {
        $fileService = $this->fileService();
        $path = $this->uploadPath();
        $bytes = \random_bytes(512);

        $fileService->putObject(
            storage: MediaStorage::Upload,
            path: $path,
            contents: $bytes,
            mimeType: MediaMimeType::fromString(self::MIME),
        );
        self::assertNotNull($fileService->headObject(MediaStorage::Upload, $path));

        $fileService->deleteObject(MediaStorage::Upload, $path);
        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));

        // Повторное удаление отсутствующего объекта не должно бросать исключение.
        $fileService->deleteObject(MediaStorage::Upload, $path);
        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));
    }

    public function testGetObjectContentsReadsBackStoredBytes(): void
    {
        $fileService = $this->fileService();
        $path = $this->uploadPath();
        $bytes = \random_bytes(777);
        $this->track(MediaStorage::Upload, $path);

        $fileService->putObject(
            storage: MediaStorage::Upload,
            path: $path,
            contents: $bytes,
            mimeType: MediaMimeType::fromString(self::MIME),
        );

        self::assertSame($bytes, $fileService->getObjectContents(MediaStorage::Upload, $path));
    }

    public function testHeadObjectReturnsNullForMissingObject(): void
    {
        self::assertNull($this->fileService()->headObject(MediaStorage::Upload, $this->uploadPath()));
    }

    public function testDownloadToFileAndUploadFromFileRoundTrip(): void
    {
        $fileService = $this->fileService();
        $sourcePath = $this->uploadPath();
        $targetPath = $this->imagesPath();
        $bytes = \random_bytes(1500);
        $this->track(MediaStorage::Upload, $sourcePath);
        $this->track(MediaStorage::Public, $targetPath);

        $fileService->putObject(
            storage: MediaStorage::Upload,
            path: $sourcePath,
            contents: $bytes,
            mimeType: MediaMimeType::fromString(self::MIME),
        );

        $localFile = $fileService->downloadToFile(MediaStorage::Upload, $sourcePath);

        self::assertFileExists($localFile);
        self::assertSame($bytes, (string) \file_get_contents($localFile));

        $fileService->uploadFromFile(
            storage: MediaStorage::Public,
            path: $targetPath,
            localFile: $localFile,
            mimeType: MediaMimeType::fromString(self::MIME),
        );
        \unlink($localFile);

        self::assertSame($bytes, $fileService->getObjectContents(MediaStorage::Public, $targetPath));
    }

    public function testAbortMultipartUploadIsIdempotent(): void
    {
        $fileService = $this->fileService();
        $path = $this->uploadPath();

        $uploadId = $fileService->createMultipartUpload(
            storage: MediaStorage::Upload,
            path: $path,
            mimeType: MediaMimeType::fromString(self::MIME),
        );

        $fileService->abortMultipartUpload(MediaStorage::Upload, $path, $uploadId);
        // Повторная отмена уже отменённой загрузки (NoSuchUpload) не должна бросать.
        $fileService->abortMultipartUpload(MediaStorage::Upload, $path, $uploadId);

        self::assertNull($fileService->headObject(MediaStorage::Upload, $path));
    }

    public function testCompleteMultipartUploadRethrowsOnInvalidPart(): void
    {
        $fileService = $this->fileService();
        $path = $this->uploadPath();

        $uploadId = $fileService->createMultipartUpload(
            storage: MediaStorage::Upload,
            path: $path,
            mimeType: MediaMimeType::fromString(self::MIME),
        );

        $this->expectException(MediaFileServiceFailedException::class);

        try {
            // Часть с таким номером не загружалась — S3 вернёт ошибку, не NoSuchUpload,
            // поэтому реализация оборачивает её в контрактное MediaFileServiceFailedException.
            $fileService->completeMultipartUpload(
                storage: MediaStorage::Upload,
                path: $path,
                uploadId: $uploadId,
                parts: new MediaMultipartPartCollection([
                    MediaMultipartPart::create(
                        partNumber: MediaMultipartPartNumber::fromInt(1),
                        eTag: MediaMultipartPartETag::fromString('"00000000000000000000000000000000"'),
                    ),
                ]),
            );
        } finally {
            $fileService->abortMultipartUpload(MediaStorage::Upload, $path, $uploadId);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->createdObjects as $createdObject) {
            $this->fileService()->deleteObject($createdObject['storage'], $createdObject['path']);
        }

        $this->createdObjects = [];

        parent::tearDown();
    }

    private function fileService(): S3MediaFileService
    {
        return new S3MediaFileService(
            storageConfig: $this->getContainer()->get(StorageConfig::class),
            mediaStorageConfig: $this->getContainer()->get(MediaStorageConfig::class),
            clientProvider: new ConfiguredS3ClientProvider(),
        );
    }

    private function httpClient(): Client
    {
        return new Client(['http_errors' => true]);
    }

    private function uploadPath(): MediaPath
    {
        $storageKey = MediaStorageKey::generate();

        return MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg');
    }

    private function imagesPath(): MediaPath
    {
        $storageKey = MediaStorageKey::generate();

        return MediaPath::fromString(\sprintf('images/%s/%s/thumbnail.jpg', $storageKey->shard(), $storageKey));
    }

    private function expiresAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('+15 minutes');
    }

    private function track(MediaStorage $storage, MediaPath $path): void
    {
        $this->createdObjects[] = ['storage' => $storage, 'path' => $path];
    }
}
