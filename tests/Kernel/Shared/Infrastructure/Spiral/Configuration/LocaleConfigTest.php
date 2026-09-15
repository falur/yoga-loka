<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Domain\Enum\Locale;
use App\Shared\Infrastructure\Spiral\Configuration\Locale\LocaleConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class LocaleConfigTest extends TestCase
{
    public function testRealLocaleConfigIsMappedFromContainer(): void
    {
        $localeConfig = $this->getContainer()->get(LocaleConfig::class);

        self::assertSame(['ru', 'en'], $localeConfig->supported);
        // Базовая локаль приходит из env(LOCALE) — проверяем не конкретное значение, а инвариант:
        // default входит в supported (точный маппинг покрыт testMapsLocaleConfigSection через стаб).
        self::assertContains($localeConfig->default, $localeConfig->supported);
    }

    public function testSupportedLocalesMatchDomainEnum(): void
    {
        $localeConfig = $this->getContainer()->get(LocaleConfig::class);

        self::assertSame(
            \array_map(
                static fn(Locale $locale): string => $locale->value,
                Locale::cases(),
            ),
            $localeConfig->supported,
        );
    }

    public function testMapsLocaleConfigSection(): void
    {
        $localeConfig = $this->mapperFor([
            'supported' => ['ru', 'en'],
            'default' => 'ru',
        ])->map(section: LocaleConfig::configName(), targetClass: LocaleConfig::class);

        self::assertSame('locale', LocaleConfig::configName());
        self::assertSame(['ru', 'en'], $localeConfig->supported);
        self::assertSame('ru', $localeConfig->default);
    }

    public function testRejectsDefaultOutsideSupported(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('locale.default');

        new LocaleConfig(supported: ['ru', 'en'], default: 'de');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->willReturnMap([[LocaleConfig::configName(), $config]]);

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
