<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Access\Domain\Collection\PermissionCollection;
use App\Modules\Access\Domain\Entity\Permission;
use App\Modules\Access\Domain\Repository\PermissionRepository;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Columns\PermissionColumns;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Entity\CyclePermissionEntity;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Mapper\PermissionMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CyclePermissionEntity>
 */
final class CyclePermissionRepository extends AbstractRepository implements PermissionRepository
{
    /**
     * @param Select<CyclePermissionEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private PermissionMapper $permissionMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(PermissionId $permissionId): Permission|null
    {
        /** @var CyclePermissionEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($permissionId->value());

        return $cycleEntity === null ? null : $this->permissionMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findBySlug(PermissionSlug $slug): Permission|null
    {
        /** @var CyclePermissionEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([PermissionColumns::SLUG => $slug->value()]);

        return $cycleEntity === null ? null : $this->permissionMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByIds(PermissionId ...$ids): PermissionCollection
    {
        $permissionCollection = new PermissionCollection();

        /** @var iterable<CyclePermissionEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(PermissionColumns::ID, 'in', new Parameter(\array_map(
                static fn(PermissionId $permissionId): string => $permissionId->value(),
                $ids,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $permissionCollection->push($this->permissionMapper->toDomain($cycleEntity));
        }

        return $permissionCollection;
    }

    /**
     * Сигнатура шире доменного интерфейса: Cycle\ORM\Select\Repository объявляет findAll()
     * с необязательными $scope и $orderBy, и сузить их в наследнике нельзя.
     *
     * Возврат тоже не сводится к родительскому: AbstractRepository<CyclePermissionEntity>
     * наследует Cycle\ORM\Select\Repository::findAll(): iterable<CyclePermissionEntity>, а
     * доменный интерфейс PermissionRepository требует PermissionCollection<Permission> — после
     * разделения Domain/Cycle Entity (волна E) типы генерика родителя и переопределения разные
     * сущности (Cycle vs домен), совместить нельзя без отказа от AbstractRepository.
     *
     * @param array<string, mixed> $scope
     * @param array<non-empty-string, non-empty-string> $orderBy
     */
    // Проверяется дважды: против Cycle\ORM\RepositoryInterface и против родителя
    // Cycle\ORM\Select\Repository — два разных сравнения типов, поэтому два подавления.
    // @phpstan-ignore method.childReturnType, method.childReturnType
    #[\Override]
    public function findAll(array $scope = [], array $orderBy = []): PermissionCollection
    {
        $permissionCollection = new PermissionCollection();

        /** @var iterable<CyclePermissionEntity> $cycleEntities */
        $cycleEntities = $this->select()->where($scope)->orderBy($orderBy)->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $permissionCollection->push($this->permissionMapper->toDomain($cycleEntity));
        }

        return $permissionCollection;
    }
}
