<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Mapping;

use App\Shared\Infrastructure\Exception\ConfigMappingException;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\Normalizer\Normalizer;
use Spiral\Config\ConfiguratorInterface;

final readonly class ConfigMapper
{
    /**
     * @param ConfiguratorInterface<object> $configurator
     * @param Normalizer<mixed> $normalizer
     */
    public function __construct(
        private ConfiguratorInterface $configurator,
        private TreeMapper $mapper,
        private Normalizer $normalizer,
    ) {}

    /**
     * @template T of object
     * @param class-string<T> $targetClass
     * @return T
     */
    public function map(string $section, string $targetClass): object
    {
        try {
            return $this->mapper->map(
                signature: $targetClass,
                source: $this->configurator->getConfig($section),
            );
        } catch (MappingError $error) {
            throw ConfigMappingException::fromMappingError(
                section: $section,
                targetClass: $targetClass,
                error: $error,
            );
        } catch (\Throwable $error) {
            if (!$error instanceof InvalidConfigValueException) {
                throw $error;
            }

            throw ConfigMappingException::fromInvalidConfigValue(
                section: $section,
                targetClass: $targetClass,
                error: $error,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(object $config): array
    {
        $normalized = $this->normalizer->normalize($config);

        if (!\is_array($normalized)) {
            throw new \LogicException('Нормализатор конфигурации должен возвращать массив для объекта.');
        }

        $result = [];

        foreach ($normalized as $key => $value) {
            if (!\is_string($key)) {
                throw new \LogicException('Нормализатор конфигурации должен возвращать массив со строковыми ключами.');
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
