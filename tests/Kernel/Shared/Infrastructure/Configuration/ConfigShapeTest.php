<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
use App\Shared\Infrastructure\Configuration\TypedConfig;
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
                'outbox',
                'push',
                'queue',
                'scaffolder',
                'session',
                'storage',
                'translator',
                'user',
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
     * @return list<string>
     */
    private function configFileSections(): array
    {
        $sections = \array_map(
            static fn(string $file): string => \basename($file, '.php'),
            \glob($this->rootDirectory() . '/app/config/*.php') ?: [],
        );
        \sort($sections);

        return \array_values($sections);
    }

    /**
     * @return list<string>
     */
    private function typedConfigSections(): array
    {
        $sections = [];
        $finder = Finder::create()
            ->files()
            ->in($this->rootDirectory() . '/app/src/Shared/Infrastructure/Configuration')
            ->name('*Config.php')
            ->sortByName();

        foreach ($finder as $file) {
            $configClass = 'App\\Shared\\Infrastructure\\Configuration\\' . \str_replace(
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

        \sort($sections);

        return \array_values($sections);
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
