<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Resource;

use App\Modules\Posts\Application\View\PostMediaItemView;
use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Application\View\TagView;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class PostResource extends AbstractResource
{
    /**
     * @param list<PostMediaItemResource> $media
     * @param list<TagResource> $tags
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
        public AuthorResource $author,
        public array $media,
        public array $tags,
        public PostResource|null $original,
    ) {}

    public static function fromView(PostView $post): self
    {
        return new self(
            id: $post->id,
            text: $post->text,
            status: $post->status,
            attachmentType: $post->attachmentType,
            likesCount: $post->likesCount,
            repostsCount: $post->repostsCount,
            commentsCount: $post->commentsCount,
            likedByMe: $post->likedByMe,
            createdAt: $post->createdAt,
            author: AuthorResource::fromView($post->author),
            media: \array_map(
                static fn(PostMediaItemView $media): PostMediaItemResource => PostMediaItemResource::fromView($media),
                $post->media,
            ),
            tags: \array_map(
                static fn(TagView $tag): TagResource => TagResource::fromView($tag),
                $post->tags,
            ),
            original: $post->original !== null ? self::fromView($post->original) : null,
        );
    }
}
