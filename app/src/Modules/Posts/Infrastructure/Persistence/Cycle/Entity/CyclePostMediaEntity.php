<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostMediaColumns;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

/**
 * Внутренняя сущность агрегата Post: своего Cycle Repository нет, выборку по её таблице ведёт
 * CyclePostRepository (PostRepository::findMediaByPostId()/findMediaByPostIds()). BelongsTo
 * сохранён буквально для той же схемы/каскада, что и до разделения (связь PostMedia -> Media
 * уже снята волной C — здесь только BelongsTo к своему корню Post), но PostMapper его не трогает:
 * связь с записью отдаётся полем postId, доменная сущность не хранит объект Post.
 */
#[Entity(
    role: 'post_media',
    table: PostMediaColumns::TABLE,
    typecast: [Typecast::class],
)]
final class CyclePostMediaEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: PostMediaColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: PostMediaColumns::POST_ID)]
    public string $postId;

    #[Column(type: 'uuid', name: PostMediaColumns::MEDIA_ID)]
    public string $mediaId;

    #[Column(type: 'integer', name: PostMediaColumns::POSITION)]
    public int $position;

    #[BelongsTo(target: CyclePostEntity::class, innerKey: 'post_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public CyclePostEntity $post;
}
