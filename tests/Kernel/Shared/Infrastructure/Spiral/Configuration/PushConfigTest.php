<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Spiral\Configuration\Push\PushConfig;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class PushConfigTest extends TestCase
{
    public function testRealPushConfigIsMappedFromContainer(): void
    {
        $pushConfig = $this->getContainer()->get(PushConfig::class);

        self::assertIsString($pushConfig->projectId);
        self::assertIsString($pushConfig->credentialsFile);
    }

    public function testMapsPushConfigSection(): void
    {
        $pushConfig = $this->mapperFor([
            'projectId' => 'yoga-loka',
            'credentialsFile' => '/secrets/fcm.json',
        ])->map(section: PushConfig::configName(), targetClass: PushConfig::class);

        self::assertSame('push', PushConfig::configName());
        self::assertSame('yoga-loka', $pushConfig->projectId);
        self::assertSame('/secrets/fcm.json', $pushConfig->credentialsFile);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator->method('getConfig')->willReturnMap([[PushConfig::configName(), $config]]);

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
