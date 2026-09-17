<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure;

use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Infrastructure\Imagick\MediaImageProcessorException;
use App\Modules\Media\Infrastructure\Imagick\ImagickMediaImageProcessor;
use App\Modules\Media\Infrastructure\Spiral\Configuration\MediaConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImagickMediaImageProcessorTest extends TestCase
{
    #[DataProvider('driverProvider')]
    public function testResizeProducesConversionWithExactDimensions(string $driver): void
    {
        $result = $this->processor($driver)->resize(
            originalContents: $this->jpegFixture(),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(80),
            targetMimeType: MediaMimeType::fromString('image/jpeg'),
        );

        self::assertSame(100, $result->width->value());
        self::assertSame(80, $result->height->value());
        self::assertSame('image/jpeg', $result->mimeType->value());
        self::assertGreaterThan(0, $result->size->value());
        self::assertSame(\strlen($result->contents), $result->size->value());
        self::assertNotSame('', $result->contents);
    }

    #[DataProvider('driverProvider')]
    public function testResizeCanConvertFormatToPng(string $driver): void
    {
        $result = $this->processor($driver)->resize(
            originalContents: $this->pngFixture(),
            width: MediaPixelDimension::fromInt(64),
            height: MediaPixelDimension::fromInt(48),
            targetMimeType: MediaMimeType::fromString('image/png'),
        );

        self::assertSame('image/png', $result->mimeType->value());
        self::assertSame(64, $result->width->value());
        self::assertSame(48, $result->height->value());
    }

    public function testUnsupportedDriverThrows(): void
    {
        $this->expectException(MediaImageProcessorException::class);

        $this->processor('bogus');
    }

    /**
     * @return list<array{string}>
     */
    public static function driverProvider(): array
    {
        return [['imagick'], ['gd']];
    }

    private function processor(string $driver): ImagickMediaImageProcessor
    {
        return new ImagickMediaImageProcessor(new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 16_777_216,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: $driver,
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
            presignedTtlSeconds: 3600,
        ));
    }

    private function jpegFixture(): string
    {
        return $this->encodeFixture(static fn($image): bool => \imagejpeg($image));
    }

    private function pngFixture(): string
    {
        return $this->encodeFixture(static fn($image): bool => \imagepng($image));
    }

    private function encodeFixture(callable $encode): string
    {
        $image = \imagecreatetruecolor(200, 150);
        \imagefill($image, 0, 0, (int) \imagecolorallocate($image, 200, 30, 30));

        \ob_start();
        $encode($image);

        return (string) \ob_get_clean();
    }
}
