<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\CommentColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CycleCommentRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'comment',
    table: CommentColumns::TABLE,
    repository: CycleCommentRepository::class,
    typecast: [Typecast::class],
)]
final class CycleCommentEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: CommentColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: CommentColumns::POST_ID)]
    public string $postId;

    #[Column(type: 'uuid', name: CommentColumns::USER_ID)]
    public string $userId;

    #[Column(type: 'text', name: CommentColumns::TEXT)]
    public string $text;

    #[Column(type: 'uuid', name: CommentColumns::PARENT_COMMENT_ID, nullable: true)]
    public string|null $parentCommentId;

    #[Column(type: 'integer', name: CommentColumns::LIKES_COUNT)]
    public int $likesCount;

    #[Column(type: 'integer', name: CommentColumns::REPLIES_COUNT)]
    public int $repliesCount;

    #[Column(type: 'datetime', name: CommentColumns::DELETED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $deletedAt;

    #[Column(type: 'uuid', name: CommentColumns::DELETED_BY_ID, nullable: true)]
    public string|null $deletedById;

    #[Column(type: 'string(500)', name: CommentColumns::DELETION_REASON, nullable: true)]
    public string|null $deletionReason;
}
