<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Cycle;

use Cycle\ORM\Collection\CollectionFactoryInterface;

final readonly class CycleCollectionFactoryConfig
{
    /**
     * @param CollectionFactoryInterface<iterable<array-key, object>> $factory
     */
    public function __construct(
        public CollectionFactoryInterface $factory,
    ) {}
}
