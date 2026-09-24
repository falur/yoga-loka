<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\Cache\CacheConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use App\Modules\System\Infrastructure\Spiral\Configuration\OpenApiConfig;
use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;
use Spiral\Config\ConfiguratorInterface;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

final class ConfigShapeTest extends TestCase
{
    public function testConfigFilesAreExpectedTypedConfigSections(): void
    {
        self::assertSame(
            [
                'cache',
                'centrifugo',
                'cycle',
                'database',
                'locale',
                'mailer',
                'media',
                'migration',
                'openapi',
                'push',
                'queue',
                'scaffolder',
                'session',
                'storage',
                'translator',
            ],
            $this->configFileSections(),
        );
    }

    public function testRootTypedConfigsMatchConfigFiles(): void
    {
        self::assertSame($this->configFileSections(), $this->typedConfigSections());
    }

    public function testSafeConfigShapeHelperDoesNotExposeSecretValues(): void
    {
        $shape = $this->configShape('storage');

        self::assertSame('string', $shape['servers']['s3']['key']);
        self::assertSame('string', $shape['servers']['s3']['secret']);
        self::assertSame('null', $shape['servers']['s3']['token']);
        self::assertSame('bool', $shape['servers']['s3']['options']['use_path_style_endpoint']);
        self::assertNotContains('yoga_loka_password', $this->flattenShape($shape));
    }

    public function testExistingTypedConfigsMapFromRealConfigurator(): void
    {
        $container = $this->getContainer();
        $configMapper = $container->get(ConfigMapper::class);

        $cacheConfig = $configMapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);
        $openApiConfig = $configMapper->map(section: OpenApiConfig::configName(), targetClass: OpenApiConfig::class);

        self::assertSame('local', $cacheConfig->default);
        self::assertSame('/api/v1', $openApiConfig->routePrefix);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function configShape(string $section): array
    {
        $config = $this->getContainer()
            ->get(ConfiguratorInterface::class)
            ->getConfig($section);

        if (!\is_array($config)) {
            return ['root' => \get_debug_type($config)];
        }

        return $this->describeConfigValue($config);
    }

    /**
     * Каталоги, где после волны G живёт конфигурация: `app/config` (секции без владельца среди
     * модулей) и `Infrastructure/Spiral/Configuration` четырёх модулей, полностью владеющих своей
     * секцией. Секции `outbox` в этом списке нет: ею владеет пакет `gian-tiaga/spiral-outbox`,
     * её разбирает его собственная фабрика, и файла-массива в проекте у неё нет.
     * `ConfigBootloader` — production-аналог этого списка (реестр каталогов, пополняемый
     * модулями в `init()`); здесь список зафиксирован явно, чтобы тест проверял результат
     * независимо от того, как production-код его считает.
     *
     * @return list<string>
     */
    private function configurationDirectories(): array
    {
        return [
            $this->rootDirectory() . '/app/src/Modules/Media/Infrastructure/Spiral/Configuration',
            $this->rootDirectory() . '/app/src/Modules/Notifications/Infrastructure/Spiral/Configuration',
            $this->rootDirectory() . '/app/src/Modules/Auth/Infrastructure/Spiral/Configuration',
            $this->rootDirectory() . '/app/src/Modules/System/Infrastructure/Spiral/Configuration',
        ];
    }

    /**
     * @return list<string>
     */
    private function configFileSections(): array
    {
        $rootSections = \array_map(
            static fn(string $file): string => \basename($file, '.php'),
            \glob($this->rootDirectory() . '/app/config/*.php') ?: [],
        );

        $sections = $rootSections;

        foreach ($this->configurationDirectories() as $directory) {
            foreach (\glob($directory . '/*.php') ?: [] as $file) {
                $basename = \basename($file, '.php');

                // Только файл-массив секции (media.php), не типизированный класс (MediaConfig.php):
                // в одном каталоге модуля лежат оба.
                if (\str_ends_with($basename, 'Config')) {
                    continue;
                }

                // Файл-фрагмент секции, которой уже владеет app/config (например storage.php модуля
                // Media — три бакета Media, которые MediaBootloader дописывает Append-ем в уже
                // существующую секцию 'storage'), не заводит новую секцию: единственный владеющий
                // файл секции 'storage' — app/config/storage.php, он уже учтён выше.
                if (\in_array(needle: $basename, haystack: $rootSections, strict: true)) {
                    continue;
                }

                $sections[] = $basename;
            }
        }

        \sort($sections);

        return \array_values($sections);
    }

    /**
     * @return list<string>
     */
    private function typedConfigSections(): array
    {
        $sections = [
            ...$this->typedConfigSectionsIn(
                directory: $this->rootDirectory() . '/app/src/Shared/Infrastructure/Spiral/Configuration',
                namespacePrefix: 'App\\Shared\\Infrastructure\\Spiral\\Configuration\\',
            ),
        ];

        foreach ($this->configurationDirectories() as $directory) {
            $namespacePrefix = 'App\\Modules\\' . \str_replace(
                search: $this->rootDirectory() . '/app/src/Modules/',
                replace: '',
                subject: $directory,
            );
            $namespacePrefix = \str_replace('/', '\\', $namespacePrefix) . '\\';

            $sections = [...$sections, ...$this->typedConfigSectionsIn($directory, $namespacePrefix)];
        }

        // Несколько typed-классов могут намеренно читать одну и ту же секцию Spiral-конфига: например
        // Shared\...\Storage\StorageConfig и Media\...\Configuration\MediaStorageConfig оба объявляют
        // configName() === 'storage' — общая секция, дополненная тремя бакетами Media через Append
        // (волна G, фаза 5, см. app/src/Modules/Media/Infrastructure/Spiral/Bootloader/MediaBootloader.php).
        // Тест сверяет НАБОР секций, у которых есть хотя бы один typed-класс, а не число классов на секцию.
        $sections = \array_unique($sections);

        \sort($sections);

        return \array_values($sections);
    }

    /**
     * @return list<string>
     */
    private function typedConfigSectionsIn(string $directory, string $namespacePrefix): array
    {
        $sections = [];
        $finder = Finder::create()
            ->files()
            ->in($directory)
            ->name('*Config.php')
            ->sortByName();

        foreach ($finder as $file) {
            $configClass = $namespacePrefix . \str_replace(
                search: ['/', '.php'],
                replace: ['\\', ''],
                subject: $file->getRelativePathname(),
            );

            if (!\is_subclass_of(object_or_class: $configClass, class: TypedConfig::class)) {
                continue;
            }

            /** @var class-string<TypedConfig> $configClass */
            $sections[] = $configClass::configName();
        }

        return $sections;
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<array-key, mixed>
     */
    private function describeConfigValue(array $config): array
    {
        $shape = [];

        foreach ($config as $key => $value) {
            $shape[$key] = \is_array($value)
                ? $this->describeConfigValue($value)
                : \get_debug_type($value);
        }

        return $shape;
    }

    /**
     * @param array<array-key, mixed> $shape
     * @return list<string>
     */
    private function flattenShape(array $shape): array
    {
        $values = [];

        foreach ($shape as $value) {
            if (\is_array($value)) {
                $values = [...$values, ...$this->flattenShape($value)];

                continue;
            }

            $values[] = (string) $value;
        }

        return $values;
    }
}
