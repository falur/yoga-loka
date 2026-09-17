<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Configuration;

use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Infrastructure\Spiral\Configuration\MediaStorageConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class MediaStorageConfigTest extends TestCase
{
    public function testRealStorageConfigContainsMediaStorageAliases(): void
    {
        $mediaStorageConfig = $this->getContainer()->get(MediaStorageConfig::class);

        foreach (MediaStorage::cases() as $mediaStorage) {
            self::assertArrayHasKey($mediaStorage->value, $mediaStorageConfig->buckets);
        }

        self::assertSame('private', $mediaStorageConfig->buckets[MediaStorage::Upload->value]->visibility);
        self::assertSame('private', $mediaStorageConfig->buckets[MediaStorage::Private->value]->visibility);
        self::assertSame('public', $mediaStorageConfig->buckets[MediaStorage::Public->value]->visibility);
    }

    public function testUploadStorageCanUseLocalServerWithoutChangingDomainEnum(): void
    {
        $mediaStorageConfig = $this->mapperFor([
            'default' => 'media-upload',
            'servers' => [
                'local' => [
                    'adapter' => 'local',
                    'directory' => '/app/public/uploads',
                    'visibility' => [
                        'public' => ['file' => 0o644, 'dir' => 0o755],
                        'private' => ['file' => 0o600, 'dir' => 0o700],
                        'default' => 'public',
                    ],
                ],
            ],
            'buckets' => [
                'media-upload' => [
                    'server' => 'local',
                    'prefix' => 'media-upload',
                    'visibility' => 'private',
                ],
            ],
        ])->map(section: MediaStorageConfig::configName(), targetClass: MediaStorageConfig::class);

        self::assertSame('media-upload', MediaStorage::Upload->value);
        self::assertSame('local', $mediaStorageConfig->buckets[MediaStorage::Upload->value]->server);
        self::assertSame('media-upload', $mediaStorageConfig->buckets[MediaStorage::Upload->value]->prefix);
    }

    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->willReturnMap([[MediaStorageConfig::configName(), $config]]);

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
