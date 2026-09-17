<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Result;

use App\Modules\Posts\Application\Data\PostData;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;

/**
 * Запись в ответе: сама запись, её счётчики, флаг «оценил я», автор, медиа, теги и, для репоста,
 * обогащённая исходная запись (один уровень вложенности).
 */
final readonly class PostResult
{
    /**
     * @param list<PostMediaResult> $media
     * @param list<TagResult> $tags
     */
    public function __construct(
        public string $id,
        public string|null $text,
        public PostStatus $status,
        public AttachmentType $attachmentType,
        public int $likesCount,
        public int $repostsCount,
        public int $commentsCount,
        public bool $likedByMe,
        public \DateTimeImmutable $createdAt,
        public AuthorResult $author,
        public array $media,
        public array $tags,
        public self|null $original,
    ) {}

    /**
     * Собирает результат из доменной записи (Entity-путь: GetPost/GetPostComments — единственная
     * запись или её оригинал репоста, читанные через PostRepository).
     *
     * @param list<PostMediaResult> $media
     * @param list<TagResult> $tags
     */
    public static function fromEntity(
        Post $post,
        AuthorResult $author,
        bool $likedByMe,
        array $media,
        array $tags,
        self|null $original,
    ): self {
        return new self(
            id: $post->id->value(),
            text: $post->text->value(),
            status: $post->status,
            attachmentType: self::resolveAttachmentType(attachmentType: $post->attachmentType, media: $media),
            likesCount: $post->likesCount->value(),
            repostsCount: $post->repostsCount->value(),
            commentsCount: $post->commentsCount->value(),
            likedByMe: $likedByMe,
            createdAt: $post->createdAt,
            author: $author,
            media: $media,
            tags: $tags,
            original: $original,
        );
    }

    /**
     * Собирает результат из данных чтения (Data-путь: GetMyFeed/GetUserFeed — страница ленты,
     * читанная через PostReader).
     *
     * @param list<PostMediaResult> $media
     * @param list<TagResult> $tags
     */
    public static function fromData(
        PostData $data,
        AuthorResult $author,
        bool $likedByMe,
        array $media,
        array $tags,
        self|null $original,
    ): self {
        return new self(
            id: $data->id,
            text: $data->text,
            status: $data->status,
            attachmentType: self::resolveAttachmentType(attachmentType: $data->attachmentType, media: $media),
            likesCount: $data->likesCount,
            repostsCount: $data->repostsCount,
            commentsCount: $data->commentsCount,
            likedByMe: $likedByMe,
            createdAt: $data->createdAt,
            author: $author,
            media: $media,
            tags: $tags,
            original: $original,
        );
    }

    /**
     * Тип вложения в ответе. Если запись помечена как media, но после мягкой деградации
     * недоступного вложения видимых медиа не осталось, отдаём none — иначе клиент получил бы
     * attachmentType "media" с пустым media и рассогласованный контракт.
     *
     * @param list<PostMediaResult> $media
     */
    private static function resolveAttachmentType(AttachmentType $attachmentType, array $media): AttachmentType
    {
        if ($attachmentType === AttachmentType::Media && $media === []) {
            return AttachmentType::None;
        }

        return $attachmentType;
    }
}
