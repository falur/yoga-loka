<?php

declare(strict_types=1);

namespace App\Modules\Access\Repository;

use App\Modules\Access\Domain\Collection\PermissionCollection;
use App\Modules\Access\Domain\Entity\Permission;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<Permission>
 */
final class PermissionRepository extends Repository
{
    public function findById(PermissionId $permissionId): Permission|null
    {
        return $this->findByPK($permissionId->value());
    }

    public function findBySlug(PermissionSlug $slug): Permission|null
    {
        return $this->findOne(['slug' => $slug->value()]);
    }

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
     * @param array<string, mixed> $scope
     * @param array<non-empty-string, non-empty-string> $orderBy
     */
    #[\Override]
    public function findAll(array $scope = [], array $orderBy = []): PermissionCollection
    {
        return new PermissionCollection($this->select()->where($scope)->orderBy($orderBy)->fetchAll());
    }
}
