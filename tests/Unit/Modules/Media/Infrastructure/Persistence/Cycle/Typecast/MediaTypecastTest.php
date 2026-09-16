<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Typecast\MediaMultipartPartCollectionTypecast;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Typecast\MediaWaveformTypecast;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\TestCase;

/**
 * MediaExpirationTypecast и MediaProcessingErrorTypecast (первая категория «Правила переноса
 * значений колонок» — null-bridging простого VO) удалены волной E, логика и тесты перенесены в
 * MediaMapperTest. MediaMultipartPartCollectionTypecast и MediaWaveformTypecast — составной JSON
 * (вторая категория), остаются Typecast-ами без изменений.
 */
final class MediaTypecastTest extends TestCase
{
    public function testMultipartPartCollectionTypecastRejectsNonArrayJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MediaMultipartPartCollectionTypecast::castDatabaseValue('123');
    }

    public function testMultipartPartCollectionTypecastRejectsNonArrayElement(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MediaMultipartPartCollectionTypecast::castDatabaseValue('[1, 2]');
    }

    public function testMultipartPartCollectionTypecastRejectsInvalidPartShape(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MediaMultipartPartCollectionTypecast::castDatabaseValue('[{"partNumber":"x","eTag":"y"}]');
    }

    public function testMultipartPartCollectionTypecastHandlesJsonCollection(): void
    {
        $parts = MediaMultipartPartCollectionTypecast::castDatabaseValue(
            '[{"partNumber":2,"eTag":"second"},{"partNumber":1,"eTag":"first"}]',
        );

        self::assertSame(1, $parts->first()->partNumber->value());
        self::assertSame(
            '[{"partNumber":1,"eTag":"first"},{"partNumber":2,"eTag":"second"}]',
            MediaMultipartPartCollectionTypecast::uncastValue($parts),
        );
    }

    public function testMultipartPartCollectionTypecastStoresDomainCollection(): void
    {
        $parts = new MediaMultipartPartCollection([
            MediaMultipartPart::create(
                partNumber: MediaMultipartPartNumber::fromInt(1),
                eTag: MediaMultipartPartETag::fromString('first'),
            ),
        ]);

        self::assertSame('[{"partNumber":1,"eTag":"first"}]', MediaMultipartPartCollectionTypecast::uncastValue($parts));
    }

    public function testWaveformTypecastRoundTripsJson(): void
    {
        $waveform = MediaWaveformTypecast::castDatabaseValue('[0,128,255]');

        self::assertSame([0, 128, 255], $waveform->peaks());
        self::assertSame('[0,128,255]', MediaWaveformTypecast::uncastValue($waveform));
        self::assertSame('[0,128,255]', MediaWaveformTypecast::uncastValue(MediaWaveform::fromPeaks([0, 128, 255])));
    }

    public function testWaveformTypecastRejectsNonArrayJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MediaWaveformTypecast::castDatabaseValue('123');
    }

    public function testWaveformTypecastRejectsNonIntElement(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MediaWaveformTypecast::castDatabaseValue('[1, "two"]');
    }

    public function testWaveformTypecastRejectsPeakOutOfRange(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaWaveformTypecast::castDatabaseValue('[0, 256]');
    }
}
