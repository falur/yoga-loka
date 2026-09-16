<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\CommentLikeColumns;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Comment: своего Cycle Repository нет, выборку по её таблице ведёт
 * CycleCommentRepository (CommentRepository::findLikeByCommentAndUser()/findLikesByUserAndCommentIds()).
 */
#[Entity(
    role: 'comment_like',
    table: CommentLikeColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CycleCommentLikeEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: CommentLikeColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: CommentLikeColumns::COMMENT_ID)]
    public string $commentId;

    #[Column(type: 'uuid', name: CommentLikeColumns::USER_ID)]
    public string $userId;
}
