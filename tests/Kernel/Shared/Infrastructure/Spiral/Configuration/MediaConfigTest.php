<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Spiral\Configuration\Media\MediaConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Exception\InvalidConfigValueException;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class MediaConfigTest extends TestCase
{
    public function testRealMediaConfigIsMappedFromContainer(): void
    {
        $mediaConfig = $this->getContainer()->get(MediaConfig::class);

        self::assertSame(86_400, $mediaConfig->stagingTtlSeconds);
        self::assertSame(3600, $mediaConfig->presignedTtlSeconds);
        self::assertSame(16_777_216, $mediaConfig->multipartThresholdBytes);
        self::assertSame(8_388_608, $mediaConfig->multipartPartSizeBytes);
        self::assertSame('imagick', $mediaConfig->imageProcessingDriver);
        self::assertSame('/usr/bin/ffmpeg', $mediaConfig->ffmpegBinaryPath);
        self::assertSame('/usr/bin/ffprobe', $mediaConfig->ffprobeBinaryPath);
        self::assertSame(1800, $mediaConfig->ffmpegTimeoutSeconds);
        self::assertSame(0, $mediaConfig->ffmpegThreads);
    }

    public function testMapsMediaConfigSection(): void
    {
        $mediaConfig = $this->mapperFor([
            'stagingTtlSeconds' => 3600,
            'presignedTtlSeconds' => 7200,
            'multipartThresholdBytes' => 20_971_520,
            'multipartPartSizeBytes' => 5_242_880,
            'imageProcessingDriver' => 'gd',
            'ffmpegBinaryPath' => '/opt/ffmpeg',
            'ffprobeBinaryPath' => '/opt/ffprobe',
            'ffmpegTimeoutSeconds' => 600,
            'ffmpegThreads' => 4,
        ])->map(section: MediaConfig::configName(), targetClass: MediaConfig::class);

        self::assertSame('media', MediaConfig::configName());
        self::assertSame(3600, $mediaConfig->stagingTtlSeconds);
        self::assertSame(7200, $mediaConfig->presignedTtlSeconds);
        self::assertSame(20_971_520, $mediaConfig->multipartThresholdBytes);
        self::assertSame(5_242_880, $mediaConfig->multipartPartSizeBytes);
        self::assertSame('gd', $mediaConfig->imageProcessingDriver);
        self::assertSame('/opt/ffmpeg', $mediaConfig->ffmpegBinaryPath);
        self::assertSame('/opt/ffprobe', $mediaConfig->ffprobeBinaryPath);
        self::assertSame(600, $mediaConfig->ffmpegTimeoutSeconds);
        self::assertSame(4, $mediaConfig->ffmpegThreads);
    }

    public function testRejectsMultipartThresholdSmallerThanPartSize(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('media.multipartThresholdBytes');

        new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 6_291_456,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
            presignedTtlSeconds: 3600,
        );
    }

    public function testRejectsPresignedTtlAboveUpperBound(): void
    {
        // Срок presigned-ссылки больше 7 суток (604800) отвергается при старте, а не падает 500 на
        // первом построении ссылки для приватного медиа.
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('media.presignedTtlSeconds');

        new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 16_777_216,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
            presignedTtlSeconds: 604_801,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->willReturnMap([[MediaConfig::configName(), $config]]);

        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()
                ->configureWith(new ConvertKeysToCamelCase())
                ->allowPermissiveTypes()
                ->allowScalarValueCasting()
                ->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }
}
