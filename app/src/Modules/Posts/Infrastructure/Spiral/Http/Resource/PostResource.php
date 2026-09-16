<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Resource;

use App\Modules\Posts\Application\Result\PostMediaResult;
use App\Modules\Posts\Application\Result\PostResult;
use App\Modules\Posts\Application\Result\TagResult;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

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

    public static function fromResult(PostResult $post): self
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
            author: AuthorResource::fromResult($post->author),
            media: \array_map(
                static fn(PostMediaResult $media): MediaResource => MediaResource::fromResult($media),
                $post->media,
            ),
            tags: \array_map(
                static fn(TagResult $tag): TagResource => TagResource::fromResult($tag),
                $post->tags,
            ),
            original: $post->original !== null ? self::fromResult($post->original) : null,
        );
    }
}
