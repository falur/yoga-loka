<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CyclePostRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\ORM\Parser\Typecast;

/**
 * Две HasMany-связи (media/tags) — реальные Cycle-relation корня, унаследованные от домена
 * без изменений, но ни один сценарий репозитория не вызывает по ним ->load(): findMediaByPostId()/
 * findMediaByPostIds()/findTagsByPostId()/findTagsByPostIds() читают вложения и метки отдельным
 * запросом (как PostMention/PostLike, у которых Cycle-relation вовсе нет). Поэтому PostMapper::
 * toDomain() эти поля не трогает (см. комментарий класса PostMapper) — тот же приём, что и у
 * Media (решения №26/№33/№34 фазы 7 волны), только здесь read-путь по relation не используется
 * нигде вообще, поэтому отдельный toDomainWithMedia()/toDomainWithTags() не нужен.
 */
#[Entity(
    role: 'post',
    table: PostColumns::TABLE,
    repository: CyclePostRepository::class,
    typecast: [Typecast::class],
)]
final class CyclePostEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: PostColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: PostColumns::USER_ID)]
    public string $userId;

    #[Column(type: 'text', name: PostColumns::TEXT, nullable: true)]
    public string|null $text;

    #[Column(type: 'string(32)', name: PostColumns::STATUS, typecast: PostStatus::class)]
    public PostStatus $status;

    #[Column(type: 'string(16)', name: PostColumns::ATTACHMENT_TYPE, typecast: AttachmentType::class)]
    public AttachmentType $attachmentType;

    #[Column(type: 'uuid', name: PostColumns::LESSON_ID, nullable: true)]
    public string|null $lessonId;

    #[Column(type: 'uuid', name: PostColumns::PRACTICE_ID, nullable: true)]
    public string|null $practiceId;

    #[Column(type: 'uuid', name: PostColumns::PARENT_POST_ID, nullable: true)]
    public string|null $parentPostId;

    #[Column(type: 'integer', name: PostColumns::LIKES_COUNT)]
    public int $likesCount;

    #[Column(type: 'integer', name: PostColumns::REPOSTS_COUNT)]
    public int $repostsCount;

    #[Column(type: 'integer', name: PostColumns::COMMENTS_COUNT)]
    public int $commentsCount;

    #[Column(type: 'datetime', name: PostColumns::DELETED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $deletedAt;

    #[HasMany(
        target: CyclePostMediaEntity::class,
        innerKey: 'id',
        outerKey: 'post_id',
        orderBy: ['position' => 'ASC'],
        collection: PostMediaCollection::class,
    )]
    public PostMediaCollection $media;

    #[HasMany(
        target: CyclePostTagEntity::class,
        innerKey: 'id',
        outerKey: 'post_id',
        collection: PostTagCollection::class,
    )]
    public PostTagCollection $tags;
}
