<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Unit\Domain\Enum;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType as DomainMediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind as DomainMediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaImageConversionType as DomainMediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType as DomainMediaVideoConversionType;
use App\Modules\Media\Public\Enum\MediaAudioConversionType;
use App\Modules\Media\Public\Enum\MediaConversionKind;
use App\Modules\Media\Public\Enum\MediaImageConversionType;
use App\Modules\Media\Public\Enum\MediaVideoConversionType;
use PHPUnit\Framework\TestCase;

/**
 * Публичные enum вида и типов конверсии — дубликаты доменных (домен не вправе зависеть от Public).
 * Преобразование между ними делается по строковому значению, поэтому расхождение набора вариантов
 * или их значений сломало бы обработку медиа в рантайме. Эти проверки держат дубликат синхронным.
 */
final class MediaConversionTypeEnumParityTest extends TestCase
{
    public function testImageConversionTypeMatchesDomainEnum(): void
    {
        self::assertSame(
            self::values(DomainMediaImageConversionType::cases()),
            self::values(MediaImageConversionType::cases()),
        );
    }

    public function testVideoConversionTypeMatchesDomainEnum(): void
    {
        self::assertSame(
            self::values(DomainMediaVideoConversionType::cases()),
            self::values(MediaVideoConversionType::cases()),
        );
    }

    public function testAudioConversionTypeMatchesDomainEnum(): void
    {
        self::assertSame(
            self::values(DomainMediaAudioConversionType::cases()),
            self::values(MediaAudioConversionType::cases()),
        );
    }

    public function testConversionKindMatchesDomainEnum(): void
    {
        self::assertSame(
            self::values(DomainMediaConversionKind::cases()),
            self::values(MediaConversionKind::cases()),
        );
    }

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return array<string, string>
     */
    private static function values(array $cases): array
    {
        $values = [];

        foreach ($cases as $case) {
            $values[$case->name] = (string) $case->value;
        }

        return $values;
    }
}
