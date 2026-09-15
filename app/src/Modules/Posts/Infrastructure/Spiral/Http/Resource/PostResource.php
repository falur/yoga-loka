<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Resource;

use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Application\View\TagView;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Media\Application\View\MediaView;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;
use App\Modules\Media\Infrastructure\Spiral\Http\Resource\MediaResource;

final readonly class PostResource extends AbstractResource
{
    /**
     * @param list<MediaResource> $media
     * @param list<TagResource> $tags
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
                static fn(MediaView $media): MediaResource => MediaResource::fromView($media),
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
