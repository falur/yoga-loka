<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Cycle;

use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
use Cycle\ORM\Collection\CollectionFactoryInterface;

final readonly class CycleCollectionsConfig
{
    /**
     * @var array<string, CycleCollectionFactoryConfig>
     */
    public array $factories;

    /**
     * @param array<string, object> $factories
     */
    public function __construct(
        public string $default,
        array $factories,
    ) {
        $collectionFactories = [];

        foreach ($factories as $name => $factory) {
            if (!$factory instanceof CollectionFactoryInterface) {
                throw new InvalidConfigValueException(
                    path: \sprintf('schema.collections.factories.%s', $name),
                    expected: CollectionFactoryInterface::class,
                    actual: \get_debug_type($factory),
                );
            }

            $collectionFactories[$name] = new CycleCollectionFactoryConfig(factory: $factory);
        }

        $this->factories = $collectionFactories;
    }
}
