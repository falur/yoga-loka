<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure;

use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Infrastructure\Storage\MediaUploadPlanner;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Modules\Media\Infrastructure\Spiral\Configuration\MediaConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaUploadPlannerTest extends TestCase
{
    public function testStagingExpirationIsTemporaryAtConfiguredTtl(): void
    {
        $planner = new MediaUploadPlanner($this->mediaConfig(stagingTtlSeconds: 86_400));

        $expiration = $planner->stagingExpiration();

        self::assertTrue($expiration->isTemporary());
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+86400 seconds')->getTimestamp(),
            $expiration->value()?->getTimestamp(),
            5,
        );
    }

    #[DataProvider('multipartThresholdProvider')]
    public function testIsMultipartComparesSizeWithThreshold(int $size, bool $expected): void
    {
        $planner = new MediaUploadPlanner(
            $this->mediaConfig(multipartThresholdBytes: 5_242_880, multipartPartSizeBytes: 5_242_880),
        );

        self::assertSame($expected, $planner->isMultipart(MediaFileSize::fromInt($size)));
    }

    /**
     * @return list<array{int, bool}>
     */
    public static function multipartThresholdProvider(): array
    {
        return [
            [5_242_880, true],
            [5_242_881, true],
            [5_242_879, false],
        ];
    }

    public function testPartSizeReflectsConfiguredPartSizeBytes(): void
    {
        $planner = new MediaUploadPlanner(
            $this->mediaConfig(multipartThresholdBytes: 16_777_216, multipartPartSizeBytes: 8_388_608),
        );

        self::assertSame(8_388_608, $planner->partSize()->value());
    }

    public function testPartsCountRoundsUp(): void
    {
        $planner = new MediaUploadPlanner(
            $this->mediaConfig(multipartThresholdBytes: 5_242_880, multipartPartSizeBytes: 5_242_880),
        );

        $partSize = $planner->partSize();

        self::assertSame(2, $planner->partsCount(size: MediaFileSize::fromInt(5_242_881), partSize: $partSize)->value());
        self::assertSame(1, $planner->partsCount(size: MediaFileSize::fromInt(5_242_880), partSize: $partSize)->value());
    }

    public function testPartSizeBelowVoMinimumThrowsDomainValueException(): void
    {
        // Конфиг валиден (порог >= размера части), но размер части ниже минимума MediaMultipartPartSize
        // (5 MiB): расчёт не должен обходить валидацию VO.
        $planner = new MediaUploadPlanner(
            $this->mediaConfig(multipartThresholdBytes: 1_048_576, multipartPartSizeBytes: 1_048_576),
        );

        $this->expectException(InvalidDomainValueException::class);

        $planner->partSize();
    }

    private function mediaConfig(
        int $stagingTtlSeconds = 86_400,
        int $multipartThresholdBytes = 5_242_880,
        int $multipartPartSizeBytes = 5_242_880,
    ): MediaConfig {
        return new MediaConfig(
            stagingTtlSeconds: $stagingTtlSeconds,
            multipartThresholdBytes: $multipartThresholdBytes,
            multipartPartSizeBytes: $multipartPartSizeBytes,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
            presignedTtlSeconds: 3600,
        );
    }
}
