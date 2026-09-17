<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostLikeColumns;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Post: своего Cycle Repository нет, выборку по её таблице ведёт
 * CyclePostRepository (PostRepository::findLikeByPostAndUser()/findLikesByUserId()/...). Как и
 * PostMention, Cycle-relation к корню не было и до разделения.
 */
#[Entity(
    role: 'post_like',
    table: PostLikeColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CyclePostLikeEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: PostLikeColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: PostLikeColumns::POST_ID)]
    public string $postId;

    #[Column(type: 'uuid', name: PostLikeColumns::USER_ID)]
    public string $userId;
}
