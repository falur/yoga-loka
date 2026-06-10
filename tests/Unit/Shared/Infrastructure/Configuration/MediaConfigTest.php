<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
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
        self::assertSame(16_777_216, $mediaConfig->multipartThresholdBytes);
        self::assertSame(8_388_608, $mediaConfig->multipartPartSizeBytes);
        self::assertSame('imagick', $mediaConfig->imageProcessingDriver);
    }

    public function testMapsMediaConfigSection(): void
    {
        $mediaConfig = $this->mapperFor([
            'stagingTtlSeconds' => 3600,
            'multipartThresholdBytes' => 20_971_520,
            'multipartPartSizeBytes' => 5_242_880,
            'imageProcessingDriver' => 'gd',
        ])->map(section: MediaConfig::configName(), targetClass: MediaConfig::class);

        self::assertSame('media', MediaConfig::configName());
        self::assertSame(3600, $mediaConfig->stagingTtlSeconds);
        self::assertSame(20_971_520, $mediaConfig->multipartThresholdBytes);
        self::assertSame(5_242_880, $mediaConfig->multipartPartSizeBytes);
        self::assertSame('gd', $mediaConfig->imageProcessingDriver);
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
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createMock(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->with(MediaConfig::configName())
            ->willReturn($config);

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
