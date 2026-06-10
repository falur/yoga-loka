<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\Storage\StorageConfig;
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
        $storageConfig = $this->getContainer()->get(StorageConfig::class);

        foreach (MediaStorage::cases() as $mediaStorage) {
            self::assertArrayHasKey($mediaStorage->value, $storageConfig->buckets);
        }

        self::assertSame('private', $storageConfig->buckets[MediaStorage::Upload->value]->visibility);
        self::assertSame('private', $storageConfig->buckets[MediaStorage::Private->value]->visibility);
        self::assertSame('public', $storageConfig->buckets[MediaStorage::Public->value]->visibility);
    }

    public function testUploadStorageCanUseLocalServerWithoutChangingDomainEnum(): void
    {
        $storageConfig = $this->mapperFor([
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
        ])->map(section: StorageConfig::configName(), targetClass: StorageConfig::class);

        self::assertSame('media-upload', MediaStorage::Upload->value);
        self::assertSame('local', $storageConfig->buckets[MediaStorage::Upload->value]->server);
        self::assertSame('media-upload', $storageConfig->buckets[MediaStorage::Upload->value]->prefix);
    }

    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createMock(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->with(StorageConfig::configName())
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
