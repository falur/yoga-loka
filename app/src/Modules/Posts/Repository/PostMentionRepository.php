<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostMentionCollection;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<PostMention>
 */
final class PostMentionRepository extends AbstractRepository
{
    public function findByPostId(PostId $postId): PostMentionCollection
    {
        return new PostMentionCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    public function findByUserId(UserId $userId): PostMentionCollection
    {
        return new PostMentionCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }
}
