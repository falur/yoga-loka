<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\CommentMentionColumns;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Comment: своего Cycle Repository нет, выборку по её таблице ведёт
 * CycleCommentRepository (CommentRepository::findMentionsByCommentId()).
 */
#[Entity(
    role: 'comment_mention',
    table: CommentMentionColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CycleCommentMentionEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: CommentMentionColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: CommentMentionColumns::COMMENT_ID)]
    public string $commentId;

    #[Column(type: 'uuid', name: CommentMentionColumns::USER_ID)]
    public string $userId;
}
