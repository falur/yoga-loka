<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\Repository\PostBlockRepository;
use App\Modules\Posts\Domain\ValueObject\PostBlockId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostBlockColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostBlockEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\PostBlockMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CyclePostBlockEntity>
 */
final class CyclePostBlockRepository extends AbstractRepository implements PostBlockRepository
{
    /**
     * @param Select<CyclePostBlockEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private PostBlockMapper $postBlockMapper,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(PostBlockId $postBlockId): PostBlock|null
    {
        /** @var CyclePostBlockEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($postBlockId->value());

        return $cycleEntity === null ? null : $this->postBlockMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findActiveByPostId(PostId $postId): PostBlock|null
    {
        /** @var CyclePostBlockEntity|null $cycleEntity */
        $cycleEntity = $this->select()
            ->where(PostBlockColumns::POST_ID, $postId->value())
            ->where(PostBlockColumns::UNBLOCKED_AT, '=', null)
            ->fetchOne();

        return $cycleEntity === null ? null : $this->postBlockMapper->toDomain($cycleEntity);
    }
}
