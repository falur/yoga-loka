<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostMentionColumns;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Post: своего Cycle Repository нет, выборку по её таблице ведёт
 * CyclePostRepository (PostRepository::findMentionsByPostId()/findMentionsByUserId()). У этой
 * сущности, в отличие от PostMedia, Cycle-relation к корню не было и до разделения — выборка
 * шла отдельным запросом, поэтому здесь нет BelongsTo.
 */
#[Entity(
    role: 'post_mention',
    table: PostMentionColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CyclePostMentionEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: PostMentionColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: PostMentionColumns::POST_ID)]
    public string $postId;

    #[Column(type: 'uuid', name: PostMentionColumns::USER_ID)]
    public string $userId;
}
