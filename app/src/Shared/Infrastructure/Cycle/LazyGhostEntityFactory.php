<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

use Cycle\ORM\Reference\ReferenceInterface;
use Cycle\ORM\RelationMap;

final class LazyGhostEntityFactory
{
    /**
     * @var \WeakMap<object, LazyGhostPendingRelationReferenceCollection>
     */
    private \WeakMap $pendingRefs;

    public function __construct(
        private readonly LazyGhostReflectionRegistry $reflectionRegistry = new LazyGhostReflectionRegistry(),
    ) {
        $this->pendingRefs = new \WeakMap();
    }

    /**
     * @param class-string $sourceClass
     */
    public function create(RelationMap $relMap, string $sourceClass): object
    {
        $reflection = $this->reflectionRegistry->reflection($sourceClass);
        $lazyGhost = $reflection->newLazyGhost($this->createInitializer($sourceClass));
        $this->pendingRefs[$lazyGhost] = new LazyGhostPendingRelationReferenceCollection();

        return $lazyGhost;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upgrade(RelationMap $relMap, object $entity, array $data): object
    {
        $reflection = $this->reflectionRegistry->reflection($entity::class);
        $relations = $relMap->getRelations();
        $hasPendingRefs = false;
        $isLazyUninitialized = $reflection->isUninitializedLazyObject($entity);

        foreach ($data as $property => $value) {
            $relation = $relations[$property] ?? null;

            if ($relation === null) {
                continue;
            }

            $reflectedProperty = $this->reflectionRegistry->property(reflection: $reflection, name: $property);

            if ($reflectedProperty === null) {
                continue;
            }

            if ($value instanceof ReferenceInterface) {
                if ($isLazyUninitialized) {
                    $pendingRefs = $this->pendingRefs[$entity] ?? new LazyGhostPendingRelationReferenceCollection();
                    $pendingRefs->set(
                        name: $property,
                        pendingRelationReference: new LazyGhostPendingRelationReference(
                            reference: $value,
                            relation: $relation,
                        ),
                    );
                    $this->pendingRefs[$entity] = $pendingRefs;
                    $hasPendingRefs = true;

                    continue;
                }

                $reflectedProperty->setValue(
                    objectOrValue: $entity,
                    value: $relation->collect(data: $relation->resolve(reference: $value, load: true)),
                );

                continue;
            }

            $reflectedProperty->setRawValueWithoutLazyInitialization(object: $entity, value: $value);
        }

        foreach ($data as $property => $value) {
            if (isset($relations[$property])) {
                continue;
            }

            $reflectedProperty = $this->reflectionRegistry->property(reflection: $reflection, name: $property);

            if ($reflectedProperty === null) {
                continue;
            }

            if ($reflectedProperty->isReadOnly() && $reflectedProperty->isInitialized($entity)) {
                continue;
            }

            $reflectedProperty->setRawValueWithoutLazyInitialization(object: $entity, value: $value);
        }

        if ($isLazyUninitialized && !$hasPendingRefs) {
            $reflection->markLazyObjectAsInitialized(object: $entity);
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractData(RelationMap $relMap, object $entity): array
    {
        $relations = $relMap->getRelations();
        /** @var array<string, mixed> $values */
        $values = [];

        foreach ($this->reflectionRegistry->extractableProperties($entity::class) as $reflectedProperty) {
            $name = $reflectedProperty->getName();

            if (isset($relations[$name]) || !$reflectedProperty->isInitialized($entity)) {
                continue;
            }

            $values[$name] = $reflectedProperty->getValue($entity);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractRelations(RelationMap $relMap, object $entity): array
    {
        /** @var array<string, mixed> $values */
        $values = [];
        $pendingRefs = $this->pendingRefs[$entity] ?? new LazyGhostPendingRelationReferenceCollection();
        $reflection = $this->reflectionRegistry->reflection($entity::class);

        foreach ($relMap->getRelations() as $name => $relation) {
            if (!\is_string($name)) {
                continue;
            }

            if ($pendingRefs->has($name)) {
                $values[$name] = $pendingRefs->get($name)->reference;

                continue;
            }

            $reflectedProperty = $this->reflectionRegistry->property(reflection: $reflection, name: $name);

            if ($reflectedProperty !== null && $reflectedProperty->isInitialized($entity)) {
                $values[$name] = $reflectedProperty->getValue($entity);
            }
        }

        return $values;
    }

    /**
     * @param class-string $class
     */
    private function createInitializer(string $class): \Closure
    {
        return function (object $entity) use ($class): void {
            $pendingRefs = $this->pendingRefs[$entity] ?? new LazyGhostPendingRelationReferenceCollection();
            $reflection = $this->reflectionRegistry->reflection($class);

            foreach ($pendingRefs->all() as $name => $pendingRef) {
                $reflection
                    ->getProperty($name)
                    ->setRawValueWithoutLazyInitialization(
                        object: $entity,
                        value: $pendingRef->relation->collect(
                            data: $pendingRef->relation->resolve(reference: $pendingRef->reference, load: true),
                        ),
                    );
            }

            unset($this->pendingRefs[$entity]);
        };
    }

}
