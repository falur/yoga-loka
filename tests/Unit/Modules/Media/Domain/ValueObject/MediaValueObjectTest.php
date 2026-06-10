<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Domain\ValueObject;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Modules\Media\Domain\ValueObject\MediaProcessingAttempts;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class MediaValueObjectTest extends TestCase
{
    public function testUuidV7IdValidatesInputAndGeneratesValue(): void
    {
        $generated = MediaId::generate();
        $restored = MediaId::fromString((string) $generated);

        self::assertTrue($generated->equals($restored));

        $this->expectException(InvalidDomainValueException::class);

        MediaId::fromString(Uuid::uuid4()->toString());
    }

    public function testStorageKeyUsesUuidV4(): void
    {
        $generated = MediaStorageKey::generate();
        $restored = MediaStorageKey::fromString((string) $generated);

        self::assertTrue($generated->equals($restored));

        $this->expectException(InvalidDomainValueException::class);

        MediaStorageKey::fromString(Uuid::uuid7()->toString());
    }

    public function testPathValidatesFormatAndShard(): void
    {
        $storageKey = MediaStorageKey::fromString('11111111-1111-4111-8111-111111111111');

        self::assertSame(
            'uploads/11/11111111-1111-4111-8111-111111111111/source.jpg',
            (string) MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
        );

        $this->expectException(InvalidDomainValueException::class);

        MediaPath::fromString('uploads/aa/11111111-1111-4111-8111-111111111111/source.jpg');
    }

    public function testMediaPathFactoriesBuildValidPaths(): void
    {
        $storageKey = MediaStorageKey::fromString('ab111111-1111-4111-8111-111111111111');

        self::assertSame(
            'images/ab/ab111111-1111-4111-8111-111111111111/thumbnail.jpg',
            (string) MediaPath::imageConversion(
                storageKey: $storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'JPG',
            ),
        );
        self::assertSame(
            'images/ab/ab111111-1111-4111-8111-111111111111/source.png',
            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Image, extension: 'png'),
        );
        self::assertSame(
            'videos/ab/ab111111-1111-4111-8111-111111111111/source.mp4',
            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Video, extension: 'mp4'),
        );
    }

    public function testMediaPathExtensionReadsLastSegment(): void
    {
        $storageKey = MediaStorageKey::fromString('ab111111-1111-4111-8111-111111111111');

        self::assertSame('jpg', MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg')->extension());
        self::assertSame('', MediaPath::fromString('uploads/ab/ab111111-1111-4111-8111-111111111111/source')->extension());
    }

    public function testOriginalReadyRejectsUnsupportedMediaType(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaPath::originalReady(storageKey: MediaStorageKey::generate(), type: MediaType::Audio, extension: 'mp3');
    }

    public function testMediaPathFactoryRejectsInvalidExtension(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaPath::imageConversion(
            storageKey: MediaStorageKey::generate(),
            type: MediaImageConversionType::Thumbnail,
            extension: 'jp g',
        );
    }

    public function testMimeTypeValidatesLength(): void
    {
        self::assertSame('image/jpeg', (string) MediaMimeType::fromString('image/jpeg'));

        $this->expectException(InvalidDomainValueException::class);

        MediaMimeType::fromString('');
    }

    /**
     * @param class-string $valueObjectClass
     */
    #[DataProvider('integerValueObjectProvider')]
    public function testIntegerValueObjectsValidateBorders(string $valueObjectClass, int $valid, int $invalid): void
    {
        $validValue = $valueObjectClass::fromInt($valid);
        self::assertSame($valid, $validValue->value());
        self::assertSame($valid, $validValue->jsonSerialize());
        self::assertSame((string) $valid, (string) $validValue);
        self::assertTrue($valueObjectClass::supports($valid));
        self::assertFalse($valueObjectClass::supports($invalid));

        $this->expectException(InvalidDomainValueException::class);

        $valueObjectClass::fromInt($invalid);
    }

    public function testProcessingAttemptsCanIncrement(): void
    {
        self::assertSame(1, MediaProcessingAttempts::zero()->increment()->value());
    }

    public function testExpirationRepresentsPermanentAndTemporaryStates(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-05-21 18:41:00');

        self::assertTrue(MediaExpiration::permanent()->isPermanent());
        self::assertSame($expiresAt, MediaExpiration::temporaryUntil($expiresAt)->expiresAtOrFail());
        self::assertNull(MediaExpiration::permanent()->value());
    }

    public function testProcessingErrorRejectsUnsafeTechnicalData(): void
    {
        self::assertSame('Не удалось обработать изображение', (string) MediaProcessingError::fromString('Не удалось обработать изображение'));
        self::assertNull(MediaProcessingError::none()->value());

        $this->expectException(InvalidDomainValueException::class);

        MediaProcessingError::fromString('Ошибка uploads/11/11111111-1111-4111-8111-111111111111/source.jpg');
    }

    public function testMultipartValuesValidateInput(): void
    {
        self::assertSame('upload-id', (string) MediaMultipartUploadIdValue::fromString('upload-id'));
        self::assertSame('etag-value', (string) MediaMultipartPartETag::fromString('etag-value'));
    }

    public function testMultipartPartCollectionSortsPartsAndRejectsDuplicates(): void
    {
        $secondPart = MediaMultipartPart::create(
            partNumber: MediaMultipartPartNumber::fromInt(2),
            eTag: MediaMultipartPartETag::fromString('second'),
        );
        $firstPart = MediaMultipartPart::create(
            partNumber: MediaMultipartPartNumber::fromInt(1),
            eTag: MediaMultipartPartETag::fromString('first'),
        );

        $parts = new MediaMultipartPartCollection([$secondPart, $firstPart]);

        self::assertSame(
            '[{"partNumber":1,"eTag":"first"},{"partNumber":2,"eTag":"second"}]',
            \json_encode(
                value: $parts->jsonSerialize(),
                flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
            ),
        );
        self::assertTrue($parts->first()->equals($firstPart));

        $this->expectException(InvalidDomainValueException::class);

        new MediaMultipartPartCollection([$firstPart, $firstPart]);
    }

    public static function integerValueObjectProvider(): iterable
    {
        yield MediaFileSize::class => [MediaFileSize::class, 1, 0];
        yield MediaPixelDimension::class => [MediaPixelDimension::class, 100_000, 100_001];
        yield MediaDuration::class => [MediaDuration::class, 604_800_000, 604_800_001];
        yield MediaBitrate::class => [MediaBitrate::class, 1_000_000_000, 1_000_000_001];
        yield MediaProcessingAttempts::class => [MediaProcessingAttempts::class, 0, -1];
        yield MediaMultipartPartsCount::class => [MediaMultipartPartsCount::class, 10_000, 10_001];
        yield MediaMultipartPartSize::class => [MediaMultipartPartSize::class, 5_242_880, 5_242_879];
        yield MediaMultipartPartNumber::class => [MediaMultipartPartNumber::class, 10_000, 10_001];
        yield MediaPresignedTtl::class => [MediaPresignedTtl::class, 604_800, 604_801];
    }
}
