<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Read;

use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Application\Data\CommentViewerFlagsData;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\CommentLikeColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentLikeEntity;
use App\Shared\Infrastructure\Persistence\Cycle\WhenSelect;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\ORMInterface;

/**
 * Чтение флага «оценил я» по набору комментариев для одного зрителя: только своя таблица лайков,
 * соседей не спрашивает, доменные сущности не создаёт.
 */
final readonly class CycleCommentViewerReader implements CommentViewerReader
{
    public function __construct(
        private ORMInterface $orm,
    ) {}

    #[\Override]
    public function likedByMe(array $commentIds, string $viewerId): CommentViewerFlagsData
    {
        if ($commentIds === []) {
            return CommentViewerFlagsData::empty();
        }

        /** @var iterable<array-key, array<non-empty-string, scalar|null>> $rows */
        $rows = $this->likeSelect()
            ->where(CommentLikeColumns::USER_ID, $viewerId)
            ->where(CommentLikeColumns::COMMENT_ID, 'in', new Parameter($commentIds))
            ->fetchData();

        return CommentViewerFlagsData::fromDatabaseRows($rows);
    }

    /** @return WhenSelect<CycleCommentLikeEntity> */
    private function likeSelect(): WhenSelect
    {
        /** @var WhenSelect<CycleCommentLikeEntity> $select */
        $select = new WhenSelect(orm: $this->orm, role: CycleCommentLikeEntity::class);

        return $select;
    }
}
