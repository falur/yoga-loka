<?php

declare(strict_types=1);

namespace App\Modules\Access\Repository;

use App\Modules\Access\Domain\Collection\RoleCollection;
use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<Role>
 */
final class RoleRepository extends Repository
{
    public function findById(RoleId $roleId): Role|null
    {
        return $this->findByPK($roleId->value());
    }

    public function findBySlug(RoleSlug $slug): Role|null
    {
        return $this->findOne(['slug' => $slug->value()]);
    }

    public function findByIds(RoleId ...$ids): RoleCollection
    {
        return new RoleCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(RoleId $roleId): string => $roleId->value(),
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
    public function findAll(array $scope = [], array $orderBy = []): RoleCollection
    {
        return new RoleCollection($this->select()->where($scope)->orderBy($orderBy)->fetchAll());
    }
}
