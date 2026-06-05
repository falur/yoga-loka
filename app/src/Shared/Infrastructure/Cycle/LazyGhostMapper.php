<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

use Cycle\ORM\Mapper\DatabaseMapper;
use Cycle\ORM\Mapper\Traits\SingleTableTrait;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\SchemaInterface;

final class LazyGhostMapper extends DatabaseMapper
{
    use SingleTableTrait;

    /**
     * @var class-string
     */
    protected string $entity;

    /**
     * @var array<string, class-string>
     */
    protected array $children = [];

    public function __construct(
        ORMInterface $orm,
        private readonly LazyGhostEntityFactory $entityFactory,
        string $role,
    ) {
        parent::__construct(orm: $orm, role: $role);

        $this->schema = $orm->getSchema();
        $this->entity = $this->entityClass(role: $role);
        $this->children = $this->children(role: $role);
        $this->discriminator = $this->discriminator(role: $role);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function init(array $data, string|null $role = null): object
    {
        return $this->entityFactory->create(
            relMap: $this->relationMap,
            sourceClass: $this->resolvedEntityClass(data: $data, role: $role),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function hydrate(object $entity, array $data): object
    {
        return $this->entityFactory->upgrade(
            relMap: $this->relationMap,
            entity: $entity,
            data: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function extract(object $entity): array
    {
        return $this->entityFactory->extractData(relMap: $this->relationMap, entity: $entity)
            + $this->entityFactory->extractRelations(relMap: $this->relationMap, entity: $entity);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function fetchFields(object $entity): array
    {
        /** @var array<string, mixed> $values */
        $values = \array_intersect_key(
            $this->entityFactory->extractData(relMap: $this->relationMap, entity: $entity),
            $this->columns + $this->parentColumns,
        ) + $this->getDiscriminatorValues($entity);

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function fetchRelations(object $entity): array
    {
        return $this->entityFactory->extractRelations(relMap: $this->relationMap, entity: $entity);
    }

    /**
     * @return class-string
     */
    private function entityClass(string $role): string
    {
        $entityClass = $this->schema->define(role: $role, property: SchemaInterface::ENTITY);

        if (!\is_string($entityClass) || !\class_exists($entityClass)) {
            throw new \UnexpectedValueException('Cycle schema вернула некорректный класс Entity.');
        }

        return $entityClass;
    }

    /**
     * @return array<string, class-string>
     */
    private function children(string $role): array
    {
        $children = $this->schema->define(role: $role, property: SchemaInterface::CHILDREN) ?? [];

        if (!\is_array($children)) {
            throw new \UnexpectedValueException('Cycle schema вернула некорректный список наследников Entity.');
        }

        $childrenByValue = [];

        foreach ($children as $value => $childClass) {
            if (!\is_string($value) || !\is_string($childClass) || !\class_exists($childClass)) {
                throw new \UnexpectedValueException('Cycle schema вернула некорректный класс наследника Entity.');
            }

            $childrenByValue[$value] = $childClass;
        }

        return $childrenByValue;
    }

    private function discriminator(string $role): string
    {
        $discriminator = $this->schema->define(role: $role, property: SchemaInterface::DISCRIMINATOR);

        if ($discriminator === null) {
            return $this->discriminator;
        }

        if (!\is_string($discriminator)) {
            throw new \UnexpectedValueException('Cycle schema вернула некорректный discriminator.');
        }

        return $discriminator;
    }

    /**
     * @param array<string, mixed> $data
     * @return class-string
     */
    private function resolvedEntityClass(array $data, string|null $role): string
    {
        $entityClass = $this->resolveClass(data: $data, role: $role);

        if (!\class_exists($entityClass)) {
            throw new \UnexpectedValueException('Cycle mapper не смог определить класс Entity.');
        }

        return $entityClass;
    }
}
