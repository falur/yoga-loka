<?php

declare(strict_types=1);

namespace App\Infrastructure\Framework\Bootloader;

use App\Infrastructure\Configuration\Cache\CacheConfig;
use App\Infrastructure\Configuration\Mapping\ConfigMapper;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;

final class ConfigBootloader extends Bootloader
{
    /**
     * @param ConfiguratorInterface<object> $configurator
     */
    public function configMapper(ConfiguratorInterface $configurator): ConfigMapper
    {
        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }

    public function cacheConfig(ConfigMapper $mapper): CacheConfig
    {
        return $mapper->map(section: 'cache', targetClass: CacheConfig::class);
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            ConfigMapper::class => [self::class, 'configMapper'],
            CacheConfig::class => [self::class, 'cacheConfig'],
        ];
    }
}
