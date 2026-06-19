<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\User\UserConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class UserConfigTest extends TestCase
{
    public function testRealUserConfigIsMappedFromContainer(): void
    {
        $userConfig = $this->getContainer()->get(UserConfig::class);

        self::assertNotSame('', $userConfig->defaultAvatarUrl);
    }

    public function testMapsUserConfigSection(): void
    {
        $userConfig = $this->mapperFor([
            'defaultAvatarUrl' => 'https://cdn.example/default.png',
        ])->map(section: UserConfig::configName(), targetClass: UserConfig::class);

        self::assertSame('user', UserConfig::configName());
        self::assertSame('https://cdn.example/default.png', $userConfig->defaultAvatarUrl);
    }

    public function testRejectsEmptyDefaultAvatarUrl(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('user.defaultAvatarUrl');

        new UserConfig(defaultAvatarUrl: '   ');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->willReturnMap([[UserConfig::configName(), $config]]);

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
