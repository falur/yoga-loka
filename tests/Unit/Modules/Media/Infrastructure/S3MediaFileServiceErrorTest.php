<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Infrastructure\Storage\MediaStorageNotConfiguredException;
use App\Modules\Media\Infrastructure\Storage\S3MediaFileService;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageBucketConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageS3OptionsConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageServerConfig;
use Aws\Command;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Media\Infrastructure\Fixture\FakeS3Client;
use Tests\Unit\Modules\Media\Infrastructure\Fixture\FakeS3ClientProvider;

final class S3MediaFileServiceErrorTest extends TestCase
{
    public function testThrowsWhenBucketAliasIsNotConfigured(): void
    {
        $fileService = new S3MediaFileService(
            storageConfig: new StorageConfig(default: 's3', servers: $this->servers(), buckets: []),
            clientProvider: new FakeS3ClientProvider(new FakeS3Client([])),
        );

        $this->expectException(MediaStorageNotConfiguredException::class);

        $fileService->headObject(MediaStorage::Upload, $this->path());
    }

    public function testThrowsWhenBucketNameIsMissing(): void
    {
        $fileService = new S3MediaFileService(
            storageConfig: new StorageConfig(
                default: 's3',
                servers: $this->servers(),
                buckets: ['media-upload' => new StorageBucketConfig(server: 's3', bucket: null)],
            ),
            clientProvider: new FakeS3ClientProvider(new FakeS3Client([])),
        );

        $this->expectException(MediaStorageNotConfiguredException::class);

        $fileService->headObject(MediaStorage::Upload, $this->path());
    }

    public function testThrowsWhenServerIsMissing(): void
    {
        $fileService = new S3MediaFileService(
            storageConfig: new StorageConfig(
                default: 's3',
                servers: $this->servers(),
                buckets: ['media-upload' => new StorageBucketConfig(server: 'ghost', bucket: 'media-upload')],
            ),
            clientProvider: new FakeS3ClientProvider(new FakeS3Client([])),
        );

        $this->expectException(MediaStorageNotConfiguredException::class);

        $fileService->headObject(MediaStorage::Upload, $this->path());
    }

    public function testThrowsWhenCreateMultipartUploadHasNoUploadId(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'createMultipartUpload' => static fn(): Result => new Result([]),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->createMultipartUpload(MediaStorage::Upload, $this->path(), $this->mime());
    }

    public function testThrowsWhenHeadObjectHasNoContentLength(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'headObject' => static fn(): Result => new Result(['ContentLength' => 'not-a-number']),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->headObject(MediaStorage::Upload, $this->path());
    }

    public function testThrowsWhenObjectBodyIsNotStream(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'getObject' => static fn(): Result => new Result(['Body' => 'plain-string']),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->getObjectContents(MediaStorage::Upload, $this->path());
    }

    public function testCompleteMultipartUploadTreatsNoSuchUploadAsSuccessWhenObjectExists(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'completeMultipartUpload' => static fn(): Result => throw self::s3Exception('NoSuchUpload'),
            'headObject' => static fn(): Result => new Result(['ContentLength' => 1024]),
        ]));

        $fileService->completeMultipartUpload(
            storage: MediaStorage::Upload,
            path: $this->path(),
            uploadId: MediaMultipartUploadIdValue::fromString('upload-id'),
            parts: new MediaMultipartPartCollection([
                MediaMultipartPart::create(
                    partNumber: MediaMultipartPartNumber::fromInt(1),
                    eTag: MediaMultipartPartETag::fromString('etag-value'),
                ),
            ]),
        );

        self::assertNotNull($fileService->headObject(MediaStorage::Upload, $this->path()));
    }

    public function testCompleteMultipartUploadRethrowsNoSuchUploadWhenObjectIsMissing(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'completeMultipartUpload' => static fn(): Result => throw self::s3Exception('NoSuchUpload'),
            // Объекта нет: headObject отвечает NoSuchKey -> isNotFound -> null -> идемпотентность не подтверждена.
            'headObject' => static fn(): Result => throw self::s3Exception('NoSuchKey'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->completeMultipartUpload(
            storage: MediaStorage::Upload,
            path: $this->path(),
            uploadId: MediaMultipartUploadIdValue::fromString('upload-id'),
            parts: $this->completedParts(),
        );
    }

    public function testCompleteMultipartUploadRethrowsNonNoSuchUploadError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'completeMultipartUpload' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->completeMultipartUpload(
            storage: MediaStorage::Upload,
            path: $this->path(),
            uploadId: MediaMultipartUploadIdValue::fromString('upload-id'),
            parts: $this->completedParts(),
        );
    }

    public function testCreateMultipartUploadWrapsAwsError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'createMultipartUpload' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->createMultipartUpload(MediaStorage::Upload, $this->path(), $this->mime());
    }

    public function testPutObjectWrapsAwsError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'putObject' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->putObject(MediaStorage::Upload, $this->path(), 'contents', $this->mime());
    }

    public function testDownloadToFileWrapsAwsError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'getObject' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->downloadToFile(MediaStorage::Upload, $this->path());
    }

    public function testDownloadToFileRemovesPartialFileWhenDownloadFails(): void
    {
        $partialFile = null;

        $fileService = $this->serviceWith(new FakeS3Client([
            // Имитируем частичную запись 'SaveAs' на диск до обрыва скачивания: S3-клиент успел
            // создать файл, затем getObject упал. Без очистки файл остался бы во временном каталоге.
            'getObject' => static function (array $args) use (&$partialFile): Result {
                $partialFile = (string) $args['SaveAs'];
                \file_put_contents($partialFile, 'partial');

                throw self::s3Exception('RequestTimeout');
            },
        ]));

        try {
            $fileService->downloadToFile(MediaStorage::Upload, $this->path());
            self::fail('Ожидалось MediaFileServiceFailedException.');
        } catch (MediaFileServiceFailedException) {
            // ожидаемо
        }

        self::assertIsString($partialFile);
        self::assertFileDoesNotExist($partialFile);
    }

    public function testUploadFromFileWrapsAwsError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'putObject' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->uploadFromFile(MediaStorage::Upload, $this->path(), '/tmp/nonexistent-source', $this->mime());
    }

    public function testCopyObjectWrapsAwsError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'copyObject' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->copyObject(
            fromStorage: MediaStorage::Upload,
            fromPath: $this->path(),
            toStorage: MediaStorage::Upload,
            toPath: $this->path(),
        );
    }

    public function testDeleteObjectRethrowsNonNotFoundError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'deleteObject' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->deleteObject(MediaStorage::Upload, $this->path());
    }

    public function testAbortMultipartUploadRethrowsNonNotFoundError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'abortMultipartUpload' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->abortMultipartUpload(
            MediaStorage::Upload,
            $this->path(),
            MediaMultipartUploadIdValue::fromString('upload-id'),
        );
    }

    public function testHeadObjectRethrowsNonNotFoundError(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'headObject' => static fn(): Result => throw self::s3Exception('AccessDenied'),
        ]));

        $this->expectException(MediaFileServiceFailedException::class);

        $fileService->headObject(MediaStorage::Upload, $this->path());
    }

    #[DataProvider('awsErrorTransienceProvider')]
    public function testClassifiesAwsErrorTransience(S3Exception $awsError, bool $expectedTransient): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'getObject' => static fn(): Result => throw $awsError,
        ]));

        try {
            $fileService->getObjectContents(MediaStorage::Upload, $this->path());
            self::fail('Ожидалось MediaFileServiceFailedException.');
        } catch (MediaFileServiceFailedException $exception) {
            self::assertSame($expectedTransient, $exception->isTransient());
        }
    }

    /**
     * @return array<string, array{S3Exception, bool}>
     */
    public static function awsErrorTransienceProvider(): array
    {
        $command = new Command('GetObject');

        return [
            'сетевая ошибка -> транзиентная' => [
                new S3Exception('connection', $command, ['connection_error' => true]),
                true,
            ],
            '5xx -> транзиентная' => [
                new S3Exception('server', $command, ['response' => new Response(503)]),
                true,
            ],
            'throttling -> транзиентная' => [
                new S3Exception('throttling', $command, ['code' => 'Throttling']),
                true,
            ],
            '4xx -> постоянная' => [
                new S3Exception('denied', $command, ['response' => new Response(403), 'code' => 'AccessDenied']),
                false,
            ],
        ];
    }

    public function testDeleteObjectIgnoresNotFound(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'deleteObject' => static fn(): Result => throw self::s3Exception('NoSuchKey'),
        ]));

        $this->expectNotToPerformAssertions();

        $fileService->deleteObject(MediaStorage::Upload, $this->path());
    }

    public function testAbortMultipartUploadIgnoresNoSuchUpload(): void
    {
        $fileService = $this->serviceWith(new FakeS3Client([
            'abortMultipartUpload' => static fn(): Result => throw self::s3Exception('NoSuchUpload'),
        ]));

        $this->expectNotToPerformAssertions();

        $fileService->abortMultipartUpload(
            MediaStorage::Upload,
            $this->path(),
            MediaMultipartUploadIdValue::fromString('upload-id'),
        );
    }

    public function testObjectKeyOmitsPrefixWhenNotConfigured(): void
    {
        $fileService = new S3MediaFileService(
            storageConfig: new StorageConfig(
                default: 's3',
                servers: $this->servers(),
                buckets: ['media-upload' => new StorageBucketConfig(server: 's3', bucket: 'media-upload', prefix: null)],
            ),
            clientProvider: new FakeS3ClientProvider(new FakeS3Client([
                'headObject' => static fn(): Result => new Result(['ContentLength' => 42]),
            ])),
        );

        $objectHead = $fileService->headObject(MediaStorage::Upload, $this->path());

        self::assertNotNull($objectHead);
        self::assertSame(42, $objectHead->contentLength->value());
    }

    private function serviceWith(FakeS3Client $client): S3MediaFileService
    {
        return new S3MediaFileService(
            storageConfig: new StorageConfig(
                default: 's3',
                servers: $this->servers(),
                buckets: ['media-upload' => new StorageBucketConfig(server: 's3', bucket: 'media-upload', prefix: 'test')],
            ),
            clientProvider: new FakeS3ClientProvider($client),
        );
    }

    /**
     * @return array<string, StorageServerConfig>
     */
    private function servers(): array
    {
        return [
            's3' => new StorageServerConfig(
                adapter: 's3',
                region: 'us-east-1',
                version: 'latest',
                endpoint: 'http://minio:9000',
                options: new StorageS3OptionsConfig(usePathStyleEndpoint: true),
            ),
        ];
    }

    private function path(): MediaPath
    {
        $storageKey = MediaStorageKey::generate();

        return MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg');
    }

    private function completedParts(): MediaMultipartPartCollection
    {
        return new MediaMultipartPartCollection([
            MediaMultipartPart::create(
                partNumber: MediaMultipartPartNumber::fromInt(1),
                eTag: MediaMultipartPartETag::fromString('etag-value'),
            ),
        ]);
    }

    private function mime(): MediaMimeType
    {
        return MediaMimeType::fromString('image/jpeg');
    }

    private static function s3Exception(string $awsErrorCode): S3Exception
    {
        return new S3Exception(
            'S3 error',
            new Command('S3Operation'),
            ['code' => $awsErrorCode],
        );
    }
}
