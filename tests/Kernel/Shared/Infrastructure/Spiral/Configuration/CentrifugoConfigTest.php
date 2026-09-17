<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Configuration;

use App\Modules\Notifications\Infrastructure\Spiral\Configuration\CentrifugoConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class CentrifugoConfigTest extends TestCase
{
    public function testRealCentrifugoConfigIsMappedFromContainer(): void
    {
        $centrifugoConfig = $this->getContainer()->get(CentrifugoConfig::class);

        // apiUrl приходит из env(CENTRIFUGO_API_URL), заданного в phpunit.xml.
        self::assertSame('http://centrifugo:8000/api', $centrifugoConfig->apiUrl);
        self::assertIsString($centrifugoConfig->apiKey);
    }

    public function testMapsCentrifugoConfigSection(): void
    {
        $centrifugoConfig = $this->mapperFor([
            'apiUrl' => 'http://centrifugo:8000/api',
            'apiKey' => 'dev-key',
        ])->map(section: CentrifugoConfig::configName(), targetClass: CentrifugoConfig::class);

        self::assertSame('centrifugo', CentrifugoConfig::configName());
        self::assertSame('http://centrifugo:8000/api', $centrifugoConfig->apiUrl);
        self::assertSame('dev-key', $centrifugoConfig->apiKey);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator->method('getConfig')->willReturnMap([[CentrifugoConfig::configName(), $config]]);

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
