<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Domain\Enum;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use PHPUnit\Framework\TestCase;

final class MediaEnumTest extends TestCase
{
    public function testMediaTypeValues(): void
    {
        self::assertSame(['image', 'video', 'audio', 'document'], $this->values(MediaType::cases()));
    }

    public function testMediaStatusValues(): void
    {
        self::assertSame(
            [
                'waitingUpload',
                'completingMultipartUpload',
                'multipartCompletionFailedCanRetry',
                'multipartCompletionFailedNeedReupload',
                'uploaded',
                'processing',
                'processingFailed',
                'ready',
                'readyOriginalRemoved',
            ],
            $this->values(MediaStatus::cases()),
        );
    }

    public function testMediaVisibilityValues(): void
    {
        self::assertSame(['private', 'public'], $this->values(MediaVisibility::cases()));
    }

    public function testMediaStorageValues(): void
    {
        self::assertSame(['media-upload', 'media-private', 'media-public'], $this->values(MediaStorage::cases()));
    }

    public function testImageConversionTypeValues(): void
    {
        self::assertSame(['thumbnail', 'preview', 'large', 'poster'], $this->values(MediaImageConversionType::cases()));
    }

    public function testVideoConversionTypeValues(): void
    {
        self::assertSame(['normalizedMp4H264'], $this->values(MediaVideoConversionType::cases()));
    }

    public function testAudioConversionTypeValues(): void
    {
        self::assertSame(['normalizedAacM4a'], $this->values(MediaAudioConversionType::cases()));
    }

    public function testConversionStatusValues(): void
    {
        self::assertSame(['processing', 'ready', 'processingFailed'], $this->values(MediaConversionStatus::cases()));
    }

    private function values(array $cases): array
    {
        return \array_map(static fn(\BackedEnum $case): string => (string) $case->value, $cases);
    }
}
