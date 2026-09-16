<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Access\Domain\Collection\RoleCollection;
use App\Modules\Access\Domain\Collection\RolePermissionCollection;
use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\Repository\RoleRepository;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Columns\RoleColumns;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Columns\RolePermissionColumns;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Entity\CycleRoleEntity;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Entity\CycleRolePermissionEntity;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Mapper\RoleMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleRoleEntity>
 */
final class CycleRoleRepository extends AbstractRepository implements RoleRepository
{
    /**
     * @param Select<CycleRoleEntity> $select
     */
    public function __construct(
        Select $select,
        private ORM $orm,
        string $role,
        private RoleMapper $roleMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(RoleId $roleId): Role|null
    {
        /** @var CycleRoleEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($roleId->value());

        return $cycleEntity === null ? null : $this->roleMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findBySlug(RoleSlug $slug): Role|null
    {
        /** @var CycleRoleEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([RoleColumns::SLUG => $slug->value()]);

        return $cycleEntity === null ? null : $this->roleMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByIds(RoleId ...$ids): RoleCollection
    {
        $roleCollection = new RoleCollection();

        /** @var iterable<CycleRoleEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(RoleColumns::ID, 'in', new Parameter(\array_map(
                static fn(RoleId $roleId): string => $roleId->value(),
                $ids,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $roleCollection->push($this->roleMapper->toDomain($cycleEntity));
        }

        return $roleCollection;
    }

    /**
     * Сигнатура шире доменного интерфейса: Cycle\ORM\Select\Repository объявляет findAll()
     * с необязательными $scope и $orderBy, и сузить их в наследнике нельзя.
     *
     * Возврат тоже не сводится к родительскому: AbstractRepository<CycleRoleEntity> наследует
     * Cycle\ORM\Select\Repository::findAll(): iterable<CycleRoleEntity>, а доменный интерфейс
     * RoleRepository требует RoleCollection<Role> — после разделения Domain/Cycle Entity
     * (волна E) типы генерика родителя и переопределения разные сущности (Cycle vs домен),
     * совместить нельзя без отказа от AbstractRepository.
     *
     * @param array<string, mixed> $scope
     * @param array<non-empty-string, non-empty-string> $orderBy
     */
    // Проверяется дважды: против Cycle\ORM\RepositoryInterface и против родителя
    // Cycle\ORM\Select\Repository — два разных сравнения типов, поэтому два подавления.
    // @phpstan-ignore method.childReturnType, method.childReturnType
    #[\Override]
    public function findAll(array $scope = [], array $orderBy = []): RoleCollection
    {
        $roleCollection = new RoleCollection();

        /** @var iterable<CycleRoleEntity> $cycleEntities */
        $cycleEntities = $this->select()->where($scope)->orderBy($orderBy)->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $roleCollection->push($this->roleMapper->toDomain($cycleEntity));
        }

        return $roleCollection;
    }

    #[\Override]
    public function findPermissions(RoleId $roleId): RolePermissionCollection
    {
        $rolePermissionCollection = new RolePermissionCollection();

        /** @var iterable<CycleRolePermissionEntity> $cycleEntities */
        $cycleEntities = $this->rolePermissionSelect()
            ->where(RolePermissionColumns::ROLE_ID, $roleId->value())
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $rolePermissionCollection->push($this->roleMapper->toRolePermissionDomain($cycleEntity));
        }

        return $rolePermissionCollection;
    }

    #[\Override]
    public function hasPermission(RoleId $roleId, PermissionId $permissionId): bool
    {
        return $this->rolePermissionSelect()->fetchOne([
            RolePermissionColumns::ROLE_ID => $roleId->value(),
            RolePermissionColumns::PERMISSION_ID => $permissionId->value(),
        ]) !== null;
    }

    /**
     * Выборка по второй таблице агрегата. Собственного репозитория у связи роли с правом нет,
     * поэтому запрос строится тем же общим примитивом, что и запрос корня.
     *
     * @return WhenSelect<CycleRolePermissionEntity>
     */
    private function rolePermissionSelect(): WhenSelect
    {
        /** @var WhenSelect<CycleRolePermissionEntity> $select */
        $select = new WhenSelect(orm: $this->orm, role: CycleRolePermissionEntity::class);
        $select->scope($this->orm->getSource(CycleRolePermissionEntity::class)->getScope());

        return $select;
    }
}
