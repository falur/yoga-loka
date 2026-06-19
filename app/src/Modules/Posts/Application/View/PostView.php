<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

/**
 * Read-model записи для ответа API: сама запись, её счётчики, флаг «оценил я», автор, медиа, теги
 * и, для репоста, обогащённая исходная запись (один уровень вложенности).
 */
final readonly class PostView
{
    /**
     * @param list<PostMediaItemView> $media
     * @param list<TagView> $tags
     */
    public function __construct(
        public string $id,
        public string|null $text,
        public string $status,
        public string $attachmentType,
        public int $likesCount,
        public int $repostsCount,
        public int $commentsCount,
        public bool $likedByMe,
        public string $createdAt,
        public AuthorView $author,
        public array $media,
        public array $tags,
        public PostView|null $original,
    ) {}
}
