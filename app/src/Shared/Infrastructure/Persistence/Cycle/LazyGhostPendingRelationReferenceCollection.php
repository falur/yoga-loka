<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Cycle;

final class LazyGhostPendingRelationReferenceCollection
{
    /**
     * @var array<string, LazyGhostPendingRelationReference>
     */
    private array $items = [];

    public function set(string $name, LazyGhostPendingRelationReference $pendingRelationReference): void
    {
        $this->items[$name] = $pendingRelationReference;
    }

    public function has(string $name): bool
    {
        return isset($this->items[$name]);
    }

    public function get(string $name): LazyGhostPendingRelationReference
    {
        return $this->items[$name]
            ?? throw new \UnexpectedValueException('Lazy relation reference не найден.');
    }

    /**
     * @return array<string, LazyGhostPendingRelationReference>
     */
    public function all(): array
    {
        return $this->items;
    }
}
