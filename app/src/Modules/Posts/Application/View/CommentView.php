<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

/**
 * Read-model комментария для ответа API: текст, ссылка на родителя (для ответа), автор, счётчики
 * и флаг «оценил я».
 */
final readonly class CommentView
{
    public function __construct(
        public string $id,
        public string $postId,
        public string|null $parentId,
        public string $text,
        public int $likesCount,
        public int $repliesCount,
        public bool $likedByMe,
        public \DateTimeImmutable $createdAt,
        public AuthorView $author,
    ) {}
}
