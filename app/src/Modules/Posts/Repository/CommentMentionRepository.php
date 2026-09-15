<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\CommentMentionCollection;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<CommentMention>
 */
final class CommentMentionRepository extends AbstractRepository
{
    public function findByCommentId(CommentId $commentId): CommentMentionCollection
    {
        return new CommentMentionCollection(
            $this->select()
                ->where('comment_id', $commentId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }
}
