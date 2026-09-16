<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetPost;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Posts\Application\Contract\PostViewerReader;
use App\Modules\Posts\Application\Result\AuthorResult;
use App\Modules\Posts\Application\Result\PostMediaResult;
use App\Modules\Posts\Application\Result\PostResult;
use App\Modules\Posts\Application\Result\TagResult;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Modules\Tags\Public\Contract\TagsContract;
use App\Modules\Tags\Public\Dto\TagDtoCollection;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Чтение одной записи. Невидимая зрителю (чужой черновик/заблокированная/удалённая) -> 404. Для
 * репоста обогащает исходную запись на один уровень вложенности (без её собственного оригинала),
 * если она видна зрителю. likedByMe — через PostViewerReader (флаг вне агрегата), сама запись и её
 * вложения/метки — через PostRepository (внутренние сущности агрегата).
 */
final readonly class GetPostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private UserContract $users,
        private MediaContract $media,
        private TagsContract $tags,
        private PostViewerReader $postViewerReader,
    ) {}

    #[LogOperation]
    public function handle(GetPostQuery $query): PostResult
    {
        $viewer = UserId::fromString($query->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($query->postId))
            ?? throw new PostNotFoundException();

        if (!PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
            throw new PostNotFoundException();
        }

        $postMedia = $this->postRepository->findMediaByPostId($post->id);
        $original = $this->visibleOriginal(post: $post, viewer: $viewer);
        $originalMedia = $original === null ? new PostMediaCollection() : $this->postRepository->findMediaByPostId($original->id);
        $mediaUrls = $this->mediaUrls($postMedia, $originalMedia);

        $likedPostIds = $this->postViewerReader->likedByMe(
            postIds: $original === null ? [$post->id->value()] : [$post->id->value(), $original->id->value()],
            viewerId: $viewer->value(),
        );

        return PostResult::fromEntity(
            post: $post,
            author: AuthorResult::fromProfile($this->users->profile($post->userId->value())),
            likedByMe: $likedPostIds->isLiked($post->id->value()),
            media: PostMediaResult::listFromPostMedia(postMedia: $postMedia, urls: $mediaUrls),
            tags: $this->postTags($this->postRepository->findTagsByPostId($post->id)),
            original: $original === null
                ? null
                : $this->originalResult(
                    original: $original,
                    viewer: $viewer,
                    postMedia: $originalMedia,
                    urls: $mediaUrls,
                    likedByMe: $likedPostIds->isLiked($original->id->value()),
                ),
        );
    }

    private function originalResult(
        Post $original,
        UserId $viewer,
        PostMediaCollection $postMedia,
        MediaDtoCollection $urls,
        bool $likedByMe,
    ): PostResult {
        $postTags = $this->postRepository->findTagsByPostId($original->id);

        return PostResult::fromEntity(
            post: $original,
            author: AuthorResult::fromProfile($this->users->profile($original->userId->value())),
            likedByMe: $likedByMe,
            media: PostMediaResult::listFromPostMedia(postMedia: $postMedia, urls: $urls),
            tags: $this->postTags($postTags),
            original: null,
        );
    }

    /**
     * @return list<TagResult>
     */
    private function postTags(PostTagCollection $postTags): array
    {
        return TagResult::listFromPostTags(postTags: $postTags, tags: $this->tagsByIds($postTags));
    }

    /**
     * Метки одним вызовом Tags. Пустой набор к соседу не ходит вовсе.
     */
    private function tagsByIds(PostTagCollection $postTags): TagDtoCollection
    {
        $tagIds = [];

        foreach ($postTags as $postTag) {
            $tagIds[] = $postTag->tagId->value();
        }

        if ($tagIds === []) {
            return new TagDtoCollection();
        }

        return $this->tags->textsByIds(\array_values(\array_unique($tagIds)));
    }

    /**
     * Исходная запись репоста, если она есть и видна зрителю.
     */
    private function visibleOriginal(Post $post, UserId $viewer): Post|null
    {
        $originalId = $post->original->value();

        if ($originalId === null) {
            return null;
        }

        $original = $this->postRepository->findById(PostId::fromString($originalId));

        if ($original === null || !PostVisibilityPolicy::isVisibleTo(post: $original, viewer: $viewer)) {
            return null;
        }

        return $original;
    }

    /**
     * Ссылки вложений записи и (если есть) её оригинала одним вызовом Media. Пустой набор к соседу
     * не ходит вовсе.
     */
    private function mediaUrls(PostMediaCollection ...$mediaCollections): MediaDtoCollection
    {
        $mediaIds = [];

        foreach ($mediaCollections as $postMediaCollection) {
            foreach ($postMediaCollection as $postMedia) {
                $mediaIds[] = $postMedia->mediaId->value();
            }
        }

        if ($mediaIds === []) {
            return new MediaDtoCollection();
        }

        return $this->media->urlsByIds(\array_values(\array_unique($mediaIds)));
    }
}
