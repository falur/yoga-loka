<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Query\GetUserFeed;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Posts\Application\Contract\PostReader;
use App\Modules\Posts\Application\Contract\PostViewerReader;
use App\Modules\Posts\Application\Data\PostData;
use App\Modules\Posts\Application\Data\PostDataCollection;
use App\Modules\Posts\Application\Data\PostViewerFlagsData;
use App\Modules\Posts\Application\Result\AuthorResult;
use App\Modules\Posts\Application\Result\PostMediaResult;
use App\Modules\Posts\Application\Result\PostResult;
use App\Modules\Posts\Application\Result\PostResultCollection;
use App\Modules\Posts\Application\Result\TagResult;
use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Tags\Public\Contract\TagsContract;
use App\Modules\Tags\Public\Dto\TagDtoCollection;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Чужая лента с cursor-пагинацией: посторонний видит только опубликованные записи владельца.
 * Страница целиком читается через PostReader (mediaIds/tagIds уже в PostData), флаг likedByMe — через
 * PostViewerReader, автор и метки — пакетно через User/Tags Public, вложения — пакетно через Media
 * Public. Оригиналы репостов страницы дочитываются через PostRepository (обычный Entity-путь) и
 * обогащаются тем же набором пакетных вызовов.
 */
final readonly class GetUserFeedHandler
{
    public function __construct(
        private PostReader $postReader,
        private PostRepository $postRepository,
        private UserContract $users,
        private MediaContract $media,
        private TagsContract $tags,
        private PostViewerReader $postViewerReader,
    ) {}

    #[LogOperation]
    public function handle(GetUserFeedQuery $query): GetUserFeedResult
    {
        $viewer = UserId::fromString($query->authUserId);

        $page = $this->postReader->userFeed(
            ownerUserId: $query->ownerUserId,
            cursor: $query->cursor,
            limit: $query->limit,
        );

        return new GetUserFeedResult(
            posts: $this->fromPage(posts: $page->posts, viewer: $viewer),
            nextCursor: $page->nextCursor,
        );
    }

    private function fromPage(PostDataCollection $posts, UserId $viewer): PostResultCollection
    {
        if ($posts->isEmpty()) {
            return new PostResultCollection();
        }

        $authors = AuthorResult::mapFromProfiles($this->users->profilesByIds($posts->authorIds()));

        $originals = $this->visibleOriginals(posts: $posts, viewer: $viewer);
        $originalIds = $this->postIds($originals);
        $originalMediaByPost = $originals->isEmpty() ? [] : $this->mediaCollectionsByPost($originalIds);
        $originalTagsByPost = $originals->isEmpty() ? [] : $this->tagCollectionsByPost($originalIds);

        $mediaUrls = $this->mediaUrls(posts: $posts, originalMediaByPost: $originalMediaByPost);
        $feedTags = $this->feedTagsBatch($posts);

        $likedPostIds = $this->postViewerReader->likedByMe(
            postIds: \array_merge($posts->ids(), $originals->mapToList(static fn(Post $original): string => $original->id->value())),
            viewerId: $viewer->value(),
        );

        $originalResults = $this->originalResults(
            originals: $originals,
            mediaByPost: $originalMediaByPost,
            urls: $mediaUrls,
            tagsByPost: $originalTagsByPost,
            likedPostIds: $likedPostIds,
        );

        return new PostResultCollection(
            $posts->toBase()->map(function (PostData $postData) use ($authors, $mediaUrls, $feedTags, $likedPostIds, $originalResults): PostResult {
                return PostResult::fromData(
                    data: $postData,
                    author: AuthorResult::require(authors: $authors, userId: $postData->authorId),
                    likedByMe: $likedPostIds->isLiked($postData->id),
                    media: PostMediaResult::listFromOrderedIds(mediaIdsInOrder: $postData->mediaIds, urls: $mediaUrls),
                    tags: TagResult::listFromIds(tagIds: $postData->tagIds, tags: $feedTags),
                    original: $postData->original !== null ? ($originalResults[$postData->original] ?? null) : null,
                );
            }),
        );
    }

    /**
     * Видимые зрителю оригиналы репостов страницы одним запросом. Без этого каждый репост дочитывал
     * бы свой оригинал по отдельности (N+1 на уровень глубже). Невидимый зрителю оригинал в набор не
     * попадает — у репоста original будет null.
     */
    private function visibleOriginals(PostDataCollection $posts, UserId $viewer): PostCollection
    {
        $originalIds = [];

        foreach ($posts as $postData) {
            if ($postData->original !== null) {
                $originalIds[$postData->original] = PostId::fromString($postData->original);
            }
        }

        if ($originalIds === []) {
            return new PostCollection();
        }

        return $this->postRepository->findByIds(...\array_values($originalIds))->filter(
            static fn(Post $original): bool => PostVisibilityPolicy::isVisibleTo(post: $original, viewer: $viewer),
        )->values();
    }

    /**
     * Обогащает оригиналы репостов страницы одним пакетом (как и сами записи): их авторы дочитываются
     * отдельным батчем (свой профиль может отличаться от авторов страницы), теги — отдельным батчем,
     * а ссылки вложений приходят из общего батча всего ответа.
     *
     * @param array<string, PostMediaCollection> $mediaByPost
     * @param array<string, PostTagCollection> $tagsByPost
     *
     * @return array<string, PostResult>
     */
    private function originalResults(
        PostCollection $originals,
        array $mediaByPost,
        MediaDtoCollection $urls,
        array $tagsByPost,
        PostViewerFlagsData $likedPostIds,
    ): array {
        if ($originals->isEmpty()) {
            return [];
        }

        $authors = AuthorResult::mapFromProfiles($this->users->profilesByIds(
            \array_values($originals->toBase()->map(static fn(Post $original): string => $original->userId->value())->unique()->all()),
        ));
        $tagDtos = $this->tagsByIds(...\array_values($tagsByPost));

        return $originals->toBase()
            ->map(function (Post $original) use ($authors, $mediaByPost, $urls, $tagsByPost, $tagDtos, $likedPostIds): PostResult {
                $originalId = $original->id->value();

                return PostResult::fromEntity(
                    post: $original,
                    author: AuthorResult::require(authors: $authors, userId: $original->userId->value()),
                    likedByMe: $likedPostIds->isLiked($originalId),
                    media: PostMediaResult::listFromPostMedia(postMedia: $mediaByPost[$originalId] ?? new PostMediaCollection(), urls: $urls),
                    tags: TagResult::listFromPostTags(postTags: $tagsByPost[$originalId] ?? new PostTagCollection(), tags: $tagDtos),
                    original: null,
                );
            })
            ->keyBy(static fn(PostResult $result): string => $result->id)
            ->all();
    }

    /**
     * Метки всех записей страницы одним вызовом Tags (без учёта оригиналов — у них свой отдельный
     * батч, как и до разделения на Reader/Repository-путь).
     */
    private function feedTagsBatch(PostDataCollection $posts): TagDtoCollection
    {
        $tagIds = [];

        foreach ($posts as $postData) {
            foreach ($postData->tagIds as $tagId) {
                $tagIds[] = $tagId;
            }
        }

        if ($tagIds === []) {
            return new TagDtoCollection();
        }

        return $this->tags->textsByIds(\array_values(\array_unique($tagIds)));
    }

    /**
     * Метки набора связей одним вызовом Tags. Пустой набор к соседу не ходит вовсе.
     */
    private function tagsByIds(PostTagCollection ...$tagCollections): TagDtoCollection
    {
        $tagIds = [];

        foreach ($tagCollections as $postTags) {
            foreach ($postTags as $postTag) {
                $tagIds[] = $postTag->tagId->value();
            }
        }

        if ($tagIds === []) {
            return new TagDtoCollection();
        }

        return $this->tags->textsByIds(\array_values(\array_unique($tagIds)));
    }

    /**
     * Ссылки вложений набора записей страницы и их видимых оригиналов одним вызовом Media: набор
     * страницы уже пришёл готовыми mediaIds от Reader, оригиналы дочитываются отдельным батчем через
     * Repository, оба набора объединяются в один вызов соседа.
     *
     * @param array<string, PostMediaCollection> $originalMediaByPost
     */
    private function mediaUrls(PostDataCollection $posts, array $originalMediaByPost): MediaDtoCollection
    {
        $mediaIds = [];

        foreach ($posts as $postData) {
            foreach ($postData->mediaIds as $mediaId) {
                $mediaIds[] = $mediaId;
            }
        }

        foreach ($originalMediaByPost as $postMediaCollection) {
            foreach ($postMediaCollection as $postMedia) {
                $mediaIds[] = $postMedia->mediaId->value();
            }
        }

        if ($mediaIds === []) {
            return new MediaDtoCollection();
        }

        return $this->media->urlsByIds(\array_values(\array_unique($mediaIds)));
    }

    /**
     * Вложения набора записей одним запросом, сгруппированные по записи (без N+1).
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostMediaCollection>
     */
    private function mediaCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postRepository->findMediaByPostIds(...$postIds) as $postMedia) {
            $byPost[$postMedia->postId->value()] ??= new PostMediaCollection();
            $byPost[$postMedia->postId->value()]->push($postMedia);
        }

        return $byPost;
    }

    /**
     * Теги набора записей одним запросом, сгруппированные по записи (без N+1).
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostTagCollection>
     */
    private function tagCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postRepository->findTagsByPostIds(...$postIds) as $postTag) {
            $byPost[$postTag->postId->value()] ??= new PostTagCollection();
            $byPost[$postTag->postId->value()]->push($postTag);
        }

        return $byPost;
    }

    /**
     * @return list<PostId>
     */
    private function postIds(PostCollection $posts): array
    {
        return $posts->mapToList(static fn(Post $post): PostId => $post->id);
    }
}
