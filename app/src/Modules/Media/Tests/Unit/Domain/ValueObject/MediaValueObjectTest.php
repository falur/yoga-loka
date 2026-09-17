<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Unit\Domain\ValueObject;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
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
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
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
        self::assertSame(
            'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Audio, extension: 'm4a'),
        );
        self::assertSame(
            'documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf',
            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Document, extension: 'pdf'),
        );
        self::assertSame(
            'videos/ab/ab111111-1111-4111-8111-111111111111/normalizedMp4H264.mp4',
            (string) MediaPath::videoConversion(
                storageKey: $storageKey,
                type: MediaVideoConversionType::NormalizedMp4H264,
                extension: 'MP4',
            ),
        );
        self::assertSame(
            'audios/ab/ab111111-1111-4111-8111-111111111111/normalizedAacM4a.m4a',
            (string) MediaPath::audioConversion(
                storageKey: $storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
        );
        self::assertSame(
            'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
            MediaPath::fromString('audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a')->value(),
        );
    }

    public function testMediaPathExtensionReadsLastSegment(): void
    {
        $storageKey = MediaStorageKey::fromString('ab111111-1111-4111-8111-111111111111');

        self::assertSame('jpg', MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg')->extension());
        self::assertSame('', MediaPath::fromString('uploads/ab/ab111111-1111-4111-8111-111111111111/source')->extension());
    }

    public function testMediaPathAcceptsDocumentsPrefix(): void
    {
        self::assertSame(
            'documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf',
            MediaPath::fromString('documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf')->value(),
        );
    }

    public function testMediaPathRejectsDocumentsPrefixWithWrongShard(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Путь файла имеет неверный раздел.');

        MediaPath::fromString('documents/cd/ab111111-1111-4111-8111-111111111111/source.pdf');
    }

    public function testWaveformValidatesPeaksAndSerializes(): void
    {
        $waveform = MediaWaveform::fromPeaks([0, 128, 255]);

        self::assertSame([0, 128, 255], $waveform->peaks());
        self::assertSame([0, 128, 255], $waveform->jsonSerialize());
        self::assertTrue($waveform->equals(MediaWaveform::fromPeaks([0, 128, 255])));
        self::assertFalse($waveform->equals(MediaWaveform::fromPeaks([0, 128, 254])));
    }

    public function testWaveformRejectsEmptyPeaks(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaWaveform::fromPeaks([]);
    }

    public function testWaveformRejectsPeakOutOfRange(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaWaveform::fromPeaks([0, 256]);
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

    public function testMimeTypeBaseValueNormalizesWithoutChangingRawValue(): void
    {
        self::assertSame('text/markdown', MediaMimeType::fromString('text/markdown;charset=utf-8')->baseValue());
        self::assertSame('application/pdf', MediaMimeType::fromString('APPLICATION/PDF')->baseValue());
        self::assertSame('image/jpeg', MediaMimeType::fromString('image/jpeg')->baseValue());
        // Пробелы вокруг параметра тоже обрезаются: остаётся только базовый MIME.
        self::assertSame('text/csv', MediaMimeType::fromString('text/csv ; charset=utf-8')->baseValue());

        // value() отдаёт исходную строку с параметрами без изменений.
        self::assertSame(
            'text/markdown;charset=utf-8',
            MediaMimeType::fromString('text/markdown;charset=utf-8')->value(),
        );
    }

    public function testMimeTypeEqualsComparesByRawValue(): void
    {
        self::assertTrue(MediaMimeType::fromString('image/jpeg')->equals(MediaMimeType::fromString('image/jpeg')));
        self::assertFalse(MediaMimeType::fromString('image/jpeg')->equals(MediaMimeType::fromString('image/png')));
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

    public function testPresignedTtlRequiresPositiveWithoutUpperBound(): void
    {
        // Домен требует лишь положительности: доменного верхнего предела у TTL нет (его держит
        // инфраструктура), поэтому значение выше прежней границы 604800 принимается.
        self::assertSame(1, MediaPresignedTtl::fromInt(1)->value());
        self::assertSame(604_801, MediaPresignedTtl::fromInt(604_801)->value());

        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('TTL presigned-ссылки должно быть положительным.');

        MediaPresignedTtl::fromInt(0);
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

    public function testProcessingAttemptsRejectsIncrementPastMaximum(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Количество попыток обработки превышено.');

        MediaProcessingAttempts::fromInt(100)->increment();
    }

    public function testMultipartUploadIdComparesAndSerializes(): void
    {
        $uploadId = MediaMultipartUploadIdValue::fromString('upload-id');

        self::assertSame('upload-id', $uploadId->value());
        self::assertSame('upload-id', $uploadId->jsonSerialize());
        self::assertTrue($uploadId->equals(MediaMultipartUploadIdValue::fromString('upload-id')));
        self::assertFalse($uploadId->equals(MediaMultipartUploadIdValue::fromString('other-id')));

        $this->expectException(InvalidDomainValueException::class);

        MediaMultipartUploadIdValue::fromString('');
    }

    public function testMultipartPartETagSerializesAndRejectsEmpty(): void
    {
        self::assertSame('etag-value', MediaMultipartPartETag::fromString('etag-value')->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        MediaMultipartPartETag::fromString('');
    }

    public function testMimeTypeSerializesToJson(): void
    {
        self::assertSame('image/png', MediaMimeType::fromString('image/png')->jsonSerialize());
    }

    public function testStorageKeySerializesToJson(): void
    {
        self::assertSame(
            '11111111-1111-4111-8111-111111111111',
            MediaStorageKey::fromString('11111111-1111-4111-8111-111111111111')->jsonSerialize(),
        );
    }

    public function testExpirationFailsWhenPermanentAskedForExpiry(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Постоянный файл не имеет даты удаления.');

        MediaExpiration::permanent()->expiresAtOrFail();
    }

    public function testExpirationComparesAndSerializes(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-05-21 18:41:00');
        $expiration = MediaExpiration::temporaryUntil($expiresAt);

        self::assertSame($expiresAt->format(\DateTimeInterface::ATOM), $expiration->jsonSerialize());
        self::assertNull(MediaExpiration::permanent()->jsonSerialize());

        self::assertTrue($expiration->equals(MediaExpiration::temporaryUntil($expiresAt)));
        self::assertTrue(MediaExpiration::permanent()->equals(MediaExpiration::permanent()));
        self::assertFalse($expiration->equals(MediaExpiration::permanent()));
        self::assertFalse($expiration->equals(MediaExpiration::temporaryUntil($expiresAt->modify('+1 second'))));

        // Контракт \Stringable: срок хранения печатается в формате ATOM, а у постоянного
        // медиа срока нет — строкой это пустое значение.
        self::assertSame($expiresAt->format(\DateTimeInterface::ATOM), (string) $expiration);
        self::assertSame('', (string) MediaExpiration::permanent());
    }

    public function testProcessingErrorSerializesAndRejectsEmpty(): void
    {
        self::assertSame(
            'Не удалось обработать изображение',
            MediaProcessingError::fromString('Не удалось обработать изображение')->jsonSerialize(),
        );

        $this->expectException(InvalidDomainValueException::class);

        MediaProcessingError::fromString('');
    }

    public function testMediaPathSerializesToJson(): void
    {
        self::assertSame(
            'uploads/11/11111111-1111-4111-8111-111111111111/source.jpg',
            MediaPath::fromString('uploads/11/11111111-1111-4111-8111-111111111111/source.jpg')->jsonSerialize(),
        );
    }

    public function testMediaPathRejectsTooLongValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Путь файла слишком длинный.');

        MediaPath::fromString(\str_repeat('a', 1025));
    }

    public function testMediaPathRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Путь файла имеет неверный формат.');

        MediaPath::fromString('not-a-valid-path');
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
        yield MediaSampleRate::class => [MediaSampleRate::class, 192_000, 192_001];
        yield MediaWaveformPeakCount::class => [MediaWaveformPeakCount::class, 4096, 4097];
    }
}
