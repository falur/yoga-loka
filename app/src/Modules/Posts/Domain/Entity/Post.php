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
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class Post
{
    use HasTimestamps;

    public private(set) PostId $id;

    public private(set) UserId $userId;

    public private(set) PostText $text;

    public private(set) PostStatus $status;

    public private(set) AttachmentType $attachmentType;

    public private(set) PostLesson $lesson;

    public private(set) PostPractice $practice;

    public private(set) PostOriginal $original;

    public private(set) LikesCount $likesCount;

    public private(set) RepostsCount $repostsCount;

    public private(set) CommentsCount $commentsCount;

    public private(set) PostDeletion $deletion;

    /**
     * Заполняется только когда репозиторий явно грузит вложения одним запросом — сегодня этого
     * не делает никто (findMediaByPostId/findMediaByPostIds читают вложения отдельным запросом,
     * не через эту связь), поэтому после findById() коллекция всегда пуста и читать её как
     * актуальное состояние некорректно.
     */
    public private(set) PostMediaCollection $media;

    /**
     * См. media — то же самое для меток записи (findTagsByPostId/findTagsByPostIds читают метки
     * отдельным запросом).
     */
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

    public static function restore(
        PostId $id,
        UserId $userId,
        PostText $text,
        PostStatus $status,
        AttachmentType $attachmentType,
        PostLesson $lesson,
        PostPractice $practice,
        PostOriginal $original,
        LikesCount $likesCount,
        RepostsCount $repostsCount,
        CommentsCount $commentsCount,
        PostDeletion $deletion,
        PostMediaCollection $media,
        PostTagCollection $tags,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $post = new self();
        $post->id = $id;
        $post->userId = $userId;
        $post->text = $text;
        $post->status = $status;
        $post->attachmentType = $attachmentType;
        $post->lesson = $lesson;
        $post->practice = $practice;
        $post->original = $original;
        $post->likesCount = $likesCount;
        $post->repostsCount = $repostsCount;
        $post->commentsCount = $commentsCount;
        $post->deletion = $deletion;
        $post->media = $media;
        $post->tags = $tags;
        $post->createdAt = $createdAt;
        $post->updatedAt = $updatedAt;

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

    /**
     * Отменяет мягкое удаление записи. Названо undelete(), а не restore(): последнее имя занято
     * технической фабрикой восстановления из хранения (docs/rules.md, «Именование»).
     */
    public function undelete(): void
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
