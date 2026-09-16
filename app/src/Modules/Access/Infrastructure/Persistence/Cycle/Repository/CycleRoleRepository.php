<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Access\Domain\Collection\RoleCollection;
use App\Modules\Access\Domain\Collection\RolePermissionCollection;
use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\Entity\RolePermission;
use App\Modules\Access\Domain\Repository\RoleRepository;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<Role>
 */
final class CycleRoleRepository extends AbstractRepository implements RoleRepository
{
    /**
     * @param Select<Role> $select
     */
    public function __construct(
        Select $select,
        private ORM $orm,
        string $role,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(RoleId $roleId): Role|null
    {
        return $this->findByPK($roleId->value());
    }

    #[\Override]
    public function findBySlug(RoleSlug $slug): Role|null
    {
        return $this->findOne(['slug' => $slug->value()]);
    }

    #[\Override]
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
     * Сигнатура шире доменного интерфейса: Cycle\ORM\Select\Repository объявляет findAll()
     * с необязательными $scope и $orderBy, и сузить их в наследнике нельзя.
     *
     * @param array<string, mixed> $scope
     * @param array<non-empty-string, non-empty-string> $orderBy
     */
    #[\Override]
    public function findAll(array $scope = [], array $orderBy = []): RoleCollection
    {
        return new RoleCollection($this->select()->where($scope)->orderBy($orderBy)->fetchAll());
    }

    #[\Override]
    public function findPermissions(RoleId $roleId): RolePermissionCollection
    {
        return new RolePermissionCollection(
            $this->rolePermissionSelect()
                ->where('role_id', $roleId->value())
                ->fetchAll(),
        );
    }

    #[\Override]
    public function hasPermission(RoleId $roleId, PermissionId $permissionId): bool
    {
        return $this->rolePermissionSelect()->fetchOne([
            'role_id' => $roleId->value(),
            'permission_id' => $permissionId->value(),
        ]) !== null;
    }

    /**
     * Выборка по второй таблице агрегата. Собственного репозитория у связи роли с правом нет,
     * поэтому запрос строится тем же общим примитивом, что и запрос корня.
     *
     * @return WhenSelect<RolePermission>
     */
    private function rolePermissionSelect(): WhenSelect
    {
        /** @var WhenSelect<RolePermission> $select */
        $select = new WhenSelect(orm: $this->orm, role: RolePermission::class);
        $select->scope($this->orm->getSource(RolePermission::class)->getScope());

        return $select;
    }
}
