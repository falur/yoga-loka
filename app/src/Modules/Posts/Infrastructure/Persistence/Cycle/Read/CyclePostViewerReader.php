<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Read;

use App\Modules\Posts\Application\Contract\PostViewerReader;
use App\Modules\Posts\Application\Data\PostViewerFlagsData;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostLikeColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostLikeEntity;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\ORMInterface;

/**
 * Чтение флага «оценил я» по набору записей для одного зрителя: только своя таблица лайков,
 * соседей не спрашивает, доменные сущности не создаёт.
 */
final readonly class CyclePostViewerReader implements PostViewerReader
{
    public function __construct(
        private ORMInterface $orm,
    ) {}

    #[\Override]
    public function likedByMe(array $postIds, string $viewerId): PostViewerFlagsData
    {
        if ($postIds === []) {
            return PostViewerFlagsData::empty();
        }

        /** @var iterable<array-key, array<non-empty-string, scalar|null>> $rows */
        $rows = $this->likeSelect()
            ->where(PostLikeColumns::USER_ID, $viewerId)
            ->where(PostLikeColumns::POST_ID, 'in', new Parameter($postIds))
            ->fetchData();

        return PostViewerFlagsData::fromDatabaseRows($rows);
    }

    /** @return WhenSelect<CyclePostLikeEntity> */
    private function likeSelect(): WhenSelect
    {
        /** @var WhenSelect<CyclePostLikeEntity> $select */
        $select = new WhenSelect(orm: $this->orm, role: CyclePostLikeEntity::class);

        return $select;
    }
}
