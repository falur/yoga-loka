<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Bootloader;

use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\BinderInterface;
use Symfony\Component\Finder\Finder;

/**
 * Реестр каталогов типизированной конфигурации. Shared-каталог зарегистрирован по умолчанию в
 * {@see self::init()}; модуль дописывает свой каталог `Infrastructure/Spiral/Configuration` через
 * {@see self::addConfigurationDirectory()} в своей фазе `init()` — по тому же принципу, что
 * `Spiral\Bootloader\I18nBootloader::addDirectory()`. Сам реестр каталогов живёт не в свойстве
 * объекта, а в отдельной секции `ConfiguratorInterface` (через `setDefaults()`/`Append`, как и у
 * `I18nBootloader`): так регистрация не зависит от того, возвращает ли контейнер один и тот же
 * экземпляр `ConfigBootloader` разным вызывающим модулям — `ConfiguratorInterface` уже является
 * единственным источником истины (framework-синглтон).
 *
 * Вычисление итоговой карты типизированных конфигов (Finder по всем зарегистрированным каталогам +
 * `bindSingleton()` в контейнер) перенесено из `defineSingletons()` в {@see self::boot()}.
 * `Spiral\Boot\BootloadManager\DefaultInvokerStrategy::invokeBootloaders()` вызывает `init()` у
 * ВСЕХ bootloader-ов приложения раньше, чем `boot()` у любого из них — поэтому к моменту, когда
 * `boot()` этого класса считает карту, все модули уже успели зарегистрировать свои каталоги в
 * своей `init()`-фазе, независимо от порядка bootloader-ов в `Kernel`.
 */
final class ConfigBootloader extends Bootloader
{
    private const string DIRECTORIES_SECTION = 'configurationDirectories';
    private const string DIRECTORIES_KEY = 'directories';
    private const string CONFIG_FILE_PATTERN = '*Config.php';
    private const string SOURCE_NAMESPACE_PREFIX = 'App\\';

    /**
     * Абсолютный путь к `app/src` — вычислен из собственного расположения класса
     * (`app/src/Shared/Infrastructure/Spiral/Bootloader`), без обращения к `DirectoriesInterface`.
     */
    private readonly string $sourceRootDirectory;

    /** @param ConfiguratorInterface<object> $config */
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {
        $this->sourceRootDirectory = \dirname(path: __DIR__, levels: 4);
    }

    /**
     * @param  ConfiguratorInterface<object>  $configurator
     */
    public function configMapper(ConfiguratorInterface $configurator): ConfigMapper
    {
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

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            ConfigMapper::class => [self::class, 'configMapper'],
        ];
    }

    public function init(): void
    {
        $this->config->setDefaults(
            section: self::DIRECTORIES_SECTION,
            data: [
                self::DIRECTORIES_KEY => [$this->sharedConfigurationDirectory()],
            ],
        );
    }

    /**
     * Модуль дописывает свой каталог `Infrastructure/Spiral/Configuration` сюда — в своей фазе
     * `init()`, до того, как {@see self::boot()} посчитает итоговую карту типизированных конфигов.
     */
    public function addConfigurationDirectory(string $directory): void
    {
        $this->config->modify(
            section: self::DIRECTORIES_SECTION,
            patch: new Append(position: self::DIRECTORIES_KEY, key: null, value: $directory),
        );
    }

    public function boot(BinderInterface $binder): void
    {
        foreach ($this->configSingletons() as $configClass => $factory) {
            $binder->bindSingleton(alias: $configClass, resolver: $factory);
        }
    }

    private function sharedConfigurationDirectory(): string
    {
        return \sprintf('%s/Shared/Infrastructure/Spiral/Configuration', $this->sourceRootDirectory);
    }

    /**
     * @return array<class-string<TypedConfig>, callable(ConfigMapper): object>
     */
    private function configSingletons(): array
    {
        $singletons = [];

        /** @var list<string> $directories */
        $directories = $this->config->getConfig(self::DIRECTORIES_SECTION)[self::DIRECTORIES_KEY];

        foreach ($directories as $directory) {
            $namespacePrefix = $this->namespacePrefixFor(directory: $directory);

            $finder = Finder::create()
                ->files()
                ->in($directory)
                ->name(self::CONFIG_FILE_PATTERN)
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

                $singletons[$configClass] = static fn(ConfigMapper $configMapper): object => $configMapper->map(
                    section: $configClass::configName(),
                    targetClass: $configClass,
                );
            }
        }

        return $singletons;
    }

    /**
     * Namespace каталога вычисляется из его положения относительно `app/src`: единственный PSR-4
     * корень проекта (`App\` => `app/src`), поэтому явный реестр «каталог => namespace» не нужен.
     */
    private function namespacePrefixFor(string $directory): string
    {
        $relative = \trim(
            string: \substr(string: $directory, offset: \strlen($this->sourceRootDirectory)),
            characters: \DIRECTORY_SEPARATOR,
        );

        return self::SOURCE_NAMESPACE_PREFIX
            . \str_replace(search: '/', replace: '\\', subject: $relative)
            . '\\';
    }
}
