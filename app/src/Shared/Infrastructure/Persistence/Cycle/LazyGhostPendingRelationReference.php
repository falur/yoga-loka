<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Cycle;

use Cycle\ORM\Reference\ReferenceInterface;
use Cycle\ORM\Relation\ActiveRelationInterface;

final readonly class LazyGhostPendingRelationReference
{
    public function __construct(
        public ReferenceInterface $reference,
        public ActiveRelationInterface $relation,
    ) {}
}
