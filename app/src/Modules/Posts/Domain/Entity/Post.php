<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentsCount;
use App\Modules\Posts\Domain\ValueObject\LikesCount;
use App\Modules\Posts\Domain\ValueObject\PostDeletion;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Domain\ValueObject\RepostsCount;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostDeletionTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostLessonTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostOriginalTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostPracticeTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostTextTypecast;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post',
    table: 'posts',
    repository: PostRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Post
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PostId::class)]
    public private(set) PostId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'text', nullable: true, typecast: PostTextTypecast::class)]
    public private(set) PostText $text;

    #[Column(type: 'string(32)', typecast: PostStatus::class)]
    public private(set) PostStatus $status;

    #[Column(type: 'string(16)', name: 'attachment_type', typecast: AttachmentType::class)]
    public private(set) AttachmentType $attachmentType;

    #[Column(type: 'uuid', name: 'lesson_id', nullable: true, typecast: PostLessonTypecast::class)]
    public private(set) PostLesson $lesson;

    #[Column(type: 'uuid', name: 'practice_id', nullable: true, typecast: PostPracticeTypecast::class)]
    public private(set) PostPractice $practice;

    #[Column(type: 'uuid', name: 'parent_post_id', nullable: true, typecast: PostOriginalTypecast::class)]
    public private(set) PostOriginal $original;

    #[Column(type: 'integer', name: 'likes_count', typecast: LikesCount::class)]
    public private(set) LikesCount $likesCount;

    #[Column(type: 'integer', name: 'reposts_count', typecast: RepostsCount::class)]
    public private(set) RepostsCount $repostsCount;

    #[Column(type: 'integer', name: 'comments_count', typecast: CommentsCount::class)]
    public private(set) CommentsCount $commentsCount;

    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: PostDeletionTypecast::class)]
    public private(set) PostDeletion $deletion;

    #[HasMany(
        target: PostMedia::class,
        innerKey: 'id',
        outerKey: 'post_id',
        orderBy: ['position' => 'ASC'],
        collection: PostMediaCollection::class,
    )]
    public private(set) PostMediaCollection $media;

    #[HasMany(
        target: PostTag::class,
        innerKey: 'id',
        outerKey: 'post_id',
        collection: PostTagCollection::class,
    )]
    public private(set) PostTagCollection $tags;

    public static function create(
        UserId $userId,
        PostText $text,
        PostStatus $status,
        AttachmentType $attachmentType,
        PostLesson $lesson,
        PostPractice $practice,
        PostOriginal $original,
    ): self {
        self::assertAttachmentIsConsistent(
            attachmentType: $attachmentType,
            lesson: $lesson,
            practice: $practice,
        );

        $post = new self();
        $post->id = PostId::generate();
        $post->userId = $userId;
        $post->text = $text;
        $post->status = $status;
        $post->attachmentType = $attachmentType;
        $post->lesson = $lesson;
        $post->practice = $practice;
        $post->original = $original;
        $post->likesCount = LikesCount::zero();
        $post->repostsCount = RepostsCount::zero();
        $post->commentsCount = CommentsCount::zero();
        $post->deletion = PostDeletion::notDeleted();
        $post->media = new PostMediaCollection();
        $post->tags = new PostTagCollection();
        $post->initializeTimestamps();

        return $post;
    }

    public function publish(): void
    {
        $this->status = PostStatus::Published;
        $this->touch();
    }

    public function block(): void
    {
        $this->status = PostStatus::Blocked;
        $this->touch();
    }

    public function unblock(): void
    {
        $this->status = PostStatus::Published;
        $this->touch();
    }

    public function softDelete(\DateTimeImmutable $deletedAt): void
    {
        $this->deletion = PostDeletion::deletedAt($deletedAt);
        $this->touch(now: $deletedAt);
    }

    public function restore(): void
    {
        $this->deletion = PostDeletion::notDeleted();
        $this->touch();
    }

    public function incrementLikes(): void
    {
        $this->likesCount = $this->likesCount->increment();
        $this->touch();
    }

    public function decrementLikes(): void
    {
        $this->likesCount = $this->likesCount->decrement();
        $this->touch();
    }

    public function incrementReposts(): void
    {
        $this->repostsCount = $this->repostsCount->increment();
        $this->touch();
    }

    public function decrementReposts(): void
    {
        $this->repostsCount = $this->repostsCount->decrement();
        $this->touch();
    }

    public function incrementComments(): void
    {
        $this->commentsCount = $this->commentsCount->increment();
        $this->touch();
    }

    public function decrementComments(): void
    {
        $this->commentsCount = $this->commentsCount->decrement();
        $this->touch();
    }

    public function setLesson(PostLesson $lesson): void
    {
        if ($lesson->isEmpty()) {
            throw new InvalidDomainValueException('Вложение-занятие требует ссылку на занятие.');
        }

        $this->clearOtherAttachments();
        $this->attachmentType = AttachmentType::Lesson;
        $this->lesson = $lesson;
        $this->touch();
    }

    public function setPractice(PostPractice $practice): void
    {
        if ($practice->isEmpty()) {
            throw new InvalidDomainValueException('Вложение-практика требует ссылку на практику.');
        }

        $this->clearOtherAttachments();
        $this->attachmentType = AttachmentType::Practice;
        $this->practice = $practice;
        $this->touch();
    }

    public function setMediaAttachment(): void
    {
        $this->clearOtherAttachments();
        $this->attachmentType = AttachmentType::Media;
        $this->touch();
    }

    public function clearAttachment(): void
    {
        $this->clearOtherAttachments();
        $this->attachmentType = AttachmentType::None;
        $this->touch();
    }

    private function clearOtherAttachments(): void
    {
        $this->lesson = PostLesson::none();
        $this->practice = PostPractice::none();
    }

    /**
     * Инвариант взаимоисключающего вложения: тип вложения и ссылки на занятие/практику
     * должны быть согласованы — ровно одна ссылка для Lesson/Practice и ни одной для None/Media.
     */
    private static function assertAttachmentIsConsistent(
        AttachmentType $attachmentType,
        PostLesson $lesson,
        PostPractice $practice,
    ): void {
        $isConsistent = match ($attachmentType) {
            AttachmentType::Lesson => !$lesson->isEmpty() && $practice->isEmpty(),
            AttachmentType::Practice => !$practice->isEmpty() && $lesson->isEmpty(),
            AttachmentType::None, AttachmentType::Media => $lesson->isEmpty() && $practice->isEmpty(),
        };

        if (!$isConsistent) {
            throw new InvalidDomainValueException(
                'Тип вложения не согласован со ссылками на занятие и практику.',
            );
        }
    }
}
