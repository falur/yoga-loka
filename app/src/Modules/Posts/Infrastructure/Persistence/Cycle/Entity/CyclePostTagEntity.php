<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostTagColumns;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Post: своего Cycle Repository нет, выборку по её таблице ведёт
 * CyclePostRepository (PostRepository::findTagsByPostId()/findTagsByPostIds()/findTagsByTagId()).
 * Ссылка на метку чужого модуля Tags хранится только как tagId (PostTagReference), без ORM-связи
 * и внешнего ключа на таблицу tags — чужая таблица принадлежит чужому модулю.
 */
#[Entity(
    role: 'post_tag',
    table: PostTagColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CyclePostTagEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: PostTagColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: PostTagColumns::POST_ID)]
    public string $postId;

    #[Column(type: 'uuid', name: PostTagColumns::TAG_ID)]
    public string $tagId;
}
