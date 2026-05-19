<?php

declare(strict_types=1);

namespace App\Infrastructure\Framework\Bootloader;

use App\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Infrastructure\Configuration\TypedConfig;
use App\Infrastructure\Framework\DirectoryAlias;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Symfony\Component\Finder\Finder;

final class ConfigBootloader extends Bootloader
{
    private const string CONFIGURATION_DIRECTORY = 'app/src/Infrastructure/Configuration';
    private const string CONFIGURATION_NAMESPACE_PREFIX = 'App\\Infrastructure\\Configuration\\';
    private const string CONFIG_FILE_PATTERN = '*Config.php';

    public function __construct(
        private readonly DirectoriesInterface $directories,
    ) {}

    /**
     * @param  ConfiguratorInterface<object>  $configurator
     */
    public function configMapper(ConfiguratorInterface $configurator): ConfigMapper
    {
        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            ConfigMapper::class => [self::class, 'configMapper'],
            ...$this->configSingletons(),
        ];
    }

    /**
     * @return array<class-string<TypedConfig>, callable(ConfigMapper): object>
     */
    private function configSingletons(): array
    {
        $singletons = [];
        $finder = Finder::create()
            ->files()
            ->in(
                \sprintf(
                    '%s/%s',
                    \rtrim(
                        string: $this->directories->get(name: DirectoryAlias::Root->value),
                        characters: \DIRECTORY_SEPARATOR,
                    ),
                    self::CONFIGURATION_DIRECTORY,
                ),
            )
            ->name(self::CONFIG_FILE_PATTERN)
            ->sortByName();

        foreach ($finder as $file) {
            $configClass = self::CONFIGURATION_NAMESPACE_PREFIX . \str_replace(
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

        return $singletons;
    }
}
