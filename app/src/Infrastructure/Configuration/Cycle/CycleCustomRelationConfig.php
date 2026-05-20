<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Cycle;

final readonly class CycleCustomRelationConfig
{
    /**
     * @param class-string $loader
     * @param class-string $relation
     */
    public function __construct(
        public string $loader,
        public string $relation,
    ) {}
}
