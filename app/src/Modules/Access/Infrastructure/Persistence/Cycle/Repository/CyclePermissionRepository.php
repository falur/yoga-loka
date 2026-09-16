<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Access\Domain\Collection\PermissionCollection;
use App\Modules\Access\Domain\Entity\Permission;
use App\Modules\Access\Domain\Repository\PermissionRepository;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

/**
 * @extends AbstractRepository<Permission>
 */
final class CyclePermissionRepository extends AbstractRepository implements PermissionRepository
{
    #[\Override]
    public function findById(PermissionId $permissionId): Permission|null
    {
        return $this->findByPK($permissionId->value());
    }

    #[\Override]
    public function findBySlug(PermissionSlug $slug): Permission|null
    {
        return $this->findOne(['slug' => $slug->value()]);
    }

    #[\Override]
    public function findByIds(PermissionId ...$ids): PermissionCollection
    {
        return new PermissionCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(PermissionId $permissionId): string => $permissionId->value(),
                    $ids,
                )))
                ->fetchAll(),
        );
    }

    /**
     * Сигнатура шире доменного интерфейса: Cycle\ORM\Select\Repository объявляет findAll()
     * с необязательными $scope и $orderBy, и сузить их в наследнике нельзя.
     *
     * @param array<string, mixed> $scope
     * @param array<non-empty-string, non-empty-string> $orderBy
     */
    #[\Override]
    public function findAll(array $scope = [], array $orderBy = []): PermissionCollection
    {
        return new PermissionCollection($this->select()->where($scope)->orderBy($orderBy)->fetchAll());
    }
}
