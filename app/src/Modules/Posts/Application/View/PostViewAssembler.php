<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\PostLikeRepository;
use App\Modules\Posts\Repository\PostMediaRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Modules\Posts\Repository\PostTagRepository;
use App\Modules\Tags\Public\Contract\TagsContract;
use App\Modules\Tags\Public\Dto\TagDtoCollection;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Собирает read-model PostView из доменной записи, обогащая её данными смежных модулей: автор —
 * через User, ссылки медиа — через публичный контракт Media, метки — через публичный контракт Tags;
 * флаг likedByMe и счётчики — из своего модуля. Для репоста доглубляет исходную запись на один
 * уровень (без её собственного оригинала), если она видна зрителю.
 *
 * В листингах пакетно собираются выборки из БД: авторы, флаги likedByMe, медиа и теги берутся одним
 * запросом на страницу (без N+1 на уровне БД). Ссылки вложений разрешаются так же, как метки:
 * идентификаторы вложений всего ответа — включая вложения оригиналов репостов — собираются в один
 * набор и уходят в Media одним вызовом MediaContract::urlsByIds, поэтому число обращений к соседу не
 * зависит от числа вложений и записей.
 */
final readonly class PostViewAssembler
{
    public function __construct(
        private UserContract $users,
        private MediaContract $media,
        private TagsContract $tags,
        private PostRepository $postRepository,
        private PostMediaRepository $postMediaRepository,
        private PostTagRepository $postTagRepository,
        private PostLikeRepository $postLikeRepository,
    ) {}

    public function fromPost(Post $post, UserId $viewer): PostView
    {
        $postTags = $this->postTagRepository->findByPostId($post->id);
        $postMedia = $this->postMediaRepository->findByPostId($post->id);
        $original = $this->visibleOriginal(post: $post, viewer: $viewer);
        $originalMedia = $original === null
            ? new PostMediaCollection()
            : $this->postMediaRepository->findByPostId($original->id);
        $mediaUrls = $this->mediaUrls($postMedia, $originalMedia);

        return $this->build(
            post: $post,
            author: $this->authorView($post->userId),
            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $post->id, userId: $viewer),
            media: $this->mediaItems(postMedia: $postMedia, urls: $mediaUrls),
            tags: $this->tagViews(postTags: $postTags, tags: $this->tagsByIds($postTags)),
            original: $original === null
                ? null
                : $this->originalView(
                    original: $original,
                    viewer: $viewer,
                    postMedia: $originalMedia,
                    urls: $mediaUrls,
                ),
        );
    }

    public function fromPosts(PostCollection $posts, UserId $viewer): PostViewCollection
    {
        if ($posts->isEmpty()) {
            return new PostViewCollection();
        }

        $postIds = $this->postIds($posts);
        $mediaByPost = $this->mediaCollectionsByPost($postIds);
        $originals = $this->visibleOriginals(posts: $posts, viewer: $viewer);
        $originalMediaByPost = $originals->isEmpty()
            ? []
            : $this->mediaCollectionsByPost($this->postIds($originals));
        $mediaUrls = $this->mediaUrls(...\array_values($mediaByPost), ...\array_values($originalMediaByPost));

        $authors = $this->authorViews($posts);
        $likedPostIds = $this->likedPostIds(posts: $posts, viewer: $viewer);
        $tagsByPost = $this->tagCollectionsByPost($postIds);
        $tagDtos = $this->tagsByIds(...\array_values($tagsByPost));
        $originalViews = $this->originalViews(
            originals: $originals,
            viewer: $viewer,
            mediaByPost: $originalMediaByPost,
            urls: $mediaUrls,
        );

        return new PostViewCollection(
            $posts->toBase()->map(function (Post $post) use ($authors, $likedPostIds, $mediaByPost, $mediaUrls, $tagsByPost, $tagDtos, $originalViews): PostView {
                $postId = $post->id->value();
                $originalId = $post->original->value();

                return $this->build(
                    post: $post,
                    author: $this->requireAuthor(authors: $authors, userId: $post->userId->value()),
                    likedByMe: isset($likedPostIds[$postId]),
                    media: $this->mediaItems(
                        postMedia: $mediaByPost[$postId] ?? new PostMediaCollection(),
                        urls: $mediaUrls,
                    ),
                    tags: $this->tagViews(postTags: $tagsByPost[$postId] ?? new PostTagCollection(), tags: $tagDtos),
                    original: $originalId !== null ? ($originalViews[$originalId] ?? null) : null,
                );
            }),
        );
    }

    /**
     * @param list<PostMediaView> $media
     * @param list<TagView> $tags
     */
    private function build(Post $post, AuthorView $author, bool $likedByMe, array $media, array $tags, PostView|null $original): PostView
    {
        return new PostView(
            id: $post->id->value(),
            text: $post->text->value(),
            status: $post->status,
            attachmentType: $this->attachmentType(post: $post, media: $media),
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
     * Тип вложения для ответа. Если запись помечена как media, но после мягкой деградации
     * недоступного вложения видимых медиа не осталось, отдаём none — иначе клиент получил бы
     * attachmentType "media" с пустым media и рассогласованный контракт.
     *
     * @param list<PostMediaView> $media
     */
    private function attachmentType(Post $post, array $media): AttachmentType
    {
        if ($post->attachmentType === AttachmentType::Media && $media === []) {
            return AttachmentType::None;
        }

        return $post->attachmentType;
    }

    private function authorView(UserId $userId): AuthorView
    {
        return AuthorView::fromProfile($this->users->profile($userId->value()));
    }

    /**
     * @return array<string, AuthorView>
     */
    private function authorViews(PostCollection $posts): array
    {
        $userIds = \array_values($posts
            ->toBase()
            ->map(static fn(Post $post): string => $post->userId->value())
            ->unique()
            ->all());

        $profiles = $this->users->profilesByIds($userIds);

        $authors = [];

        foreach ($profiles as $profile) {
            $authors[$profile->userId] = AuthorView::fromProfile($profile);
        }

        return $authors;
    }

    /**
     * Берёт автора из пакетной карты профилей. Пакетное чтение профилей молча опускает
     * отсутствующих, поэтому отсутствие ключа обрабатываем явно — той же 404, что и одиночный путь
     * (fromPost -> UserContract::profile), а не неконтролируемым undefined array key -> 500.
     * Ключ перевода свой: текст совпадает с текстом владельца, но чужими ключами Posts не бросает.
     *
     * @param array<string, AuthorView> $authors
     */
    private function requireAuthor(array $authors, string $userId): AuthorView
    {
        return $authors[$userId] ?? throw new NotFoundException('app.posts.author_not_found');
    }

    /**
     * @return array<string, true>
     */
    private function likedPostIds(PostCollection $posts, UserId $viewer): array
    {
        $postIds = $posts->mapToList(static fn(Post $post): PostId => $post->id);
        $liked = [];

        foreach ($this->postLikeRepository->findByUserAndPostIds($viewer, ...$postIds) as $like) {
            $liked[$like->postId->value()] = true;
        }

        return $liked;
    }

    /**
     * Медиа набора записей одним запросом, сгруппированные по записи (без N+1 в листинге).
     * Значение — типизированная коллекция вложений, поэтому контракт не содержит вложенных массивов.
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostMediaCollection>
     */
    private function mediaCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postMediaRepository->findByPostIds(...$postIds) as $postMedia) {
            $byPost[$postMedia->postId->value()] ??= new PostMediaCollection();
            $byPost[$postMedia->postId->value()]->push($postMedia);
        }

        return $byPost;
    }

    /**
     * Теги набора записей одним запросом, сгруппированные по записи (без N+1 в листинге).
     *
     * @param list<PostId> $postIds
     *
     * @return array<string, PostTagCollection>
     */
    private function tagCollectionsByPost(array $postIds): array
    {
        $byPost = [];

        foreach ($this->postTagRepository->findByPostIds(...$postIds) as $postTag) {
            $byPost[$postTag->postId->value()] ??= new PostTagCollection();
            $byPost[$postTag->postId->value()]->push($postTag);
        }

        return $byPost;
    }

    /**
     * Ссылки вложений набора связей одним вызовом Media. Несколько коллекций (по записи в листинге и
     * по оригиналам репостов) собираются в один батч, чтобы не ходить к соседу на каждое вложение.
     * Пустой набор к соседу не ходит вовсе.
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

    /**
     * @return list<PostMediaView>
     */
    private function mediaItems(PostMediaCollection $postMedia, MediaDtoCollection $urls): array
    {
        $items = [];

        foreach ($postMedia as $item) {
            $view = $this->mediaItem(postMedia: $item, urls: $urls);

            if ($view === null) {
                continue;
            }

            $items[] = $view;
        }

        return $items;
    }

    /**
     * Берёт ссылки вложения из общего батча Media по идентификатору медиа. Позиция вложения
     * принадлежит записи, поэтому её добавляет Posts, а не сосед.
     *
     * Показывать нечего: медиа недоступно (в батче его нет — не найдено или не финализировано) или
     * у него нет ни оригинала, ни конверсий -> вложение исключается (мягкая деградация, без 500).
     * Оригинал мог быть удалён (readyOriginalRemoved) — тогда original = null, но по оставшимся
     * конверсиям вложение показывается.
     */
    private function mediaItem(PostMedia $postMedia, MediaDtoCollection $urls): PostMediaView|null
    {
        $media = $urls->get($postMedia->mediaId->value());

        if ($media === null || ($media->original === null && $media->conversions === [])) {
            return null;
        }

        return new PostMediaView(
            id: $media->id,
            position: $postMedia->position->value(),
            original: $media->original,
            conversions: $media->conversions,
        );
    }

    /**
     * Метки набора связей одним вызовом Tags. Несколько коллекций (по записи в листинге) собираются в
     * один батч, чтобы не ходить к соседу на каждую запись. Пустой набор к соседу не ходит вовсе.
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
     * Строит представления меток записи из общего батча. В листинге батч содержит метки всех записей
     * страницы, поэтому перебираются связи самой записи, а метка берётся из батча по идентификатору
     * за O(1): так форма и порядок чужого набора на ответ не влияют.
     *
     * Метки нет в батче — её не существует у соседа, поэтому связь пропускается (мягкая деградация,
     * без 500). Порядок меток в ответе частью контракта не является (в post_tags нет колонки позиции
     * — порядок добавления не хранится).
     *
     * @return list<TagView>
     */
    private function tagViews(PostTagCollection $postTags, TagDtoCollection $tags): array
    {
        $views = [];

        foreach ($postTags as $postTag) {
            $tag = $tags->get($postTag->tagId->value());

            if ($tag === null) {
                continue;
            }

            $views[] = new TagView(id: $tag->id, text: $tag->text);
        }

        return $views;
    }

    /**
     * @return list<PostId>
     */
    private function postIds(PostCollection $posts): array
    {
        return $posts->mapToList(static fn(Post $post): PostId => $post->id);
    }

    /**
     * Исходная запись репоста, если она есть и видна зрителю. Отделена от сборки представления,
     * потому что её вложения попадают в тот же батч ссылок медиа, что и вложения самой записи.
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

    private function originalView(
        Post $original,
        UserId $viewer,
        PostMediaCollection $postMedia,
        MediaDtoCollection $urls,
    ): PostView {
        $postTags = $this->postTagRepository->findByPostId($original->id);

        return $this->build(
            post: $original,
            author: $this->authorView($original->userId),
            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $original->id, userId: $viewer),
            media: $this->mediaItems(postMedia: $postMedia, urls: $urls),
            tags: $this->tagViews(postTags: $postTags, tags: $this->tagsByIds($postTags)),
            original: null,
        );
    }

    /**
     * Видимые зрителю оригиналы репостов страницы одним запросом. Без этого каждый репост дочитывал
     * бы свой оригинал по отдельности (N+1 на уровень глубже). Невидимый зрителю оригинал в набор не
     * попадает — у репоста original будет null.
     */
    private function visibleOriginals(PostCollection $posts, UserId $viewer): PostCollection
    {
        $originalIds = [];

        foreach ($posts as $post) {
            $originalId = $post->original->value();

            if ($originalId !== null) {
                $originalIds[$originalId] = PostId::fromString($originalId);
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
     * Обогащает оригиналы репостов страницы одним пакетом (как и сами записи): их авторы, теги и
     * флаги likedByMe берутся одним запросом, а ссылки вложений приходят из общего батча всего
     * ответа.
     *
     * @param array<string, PostMediaCollection> $mediaByPost
     *
     * @return array<string, PostView>
     */
    private function originalViews(
        PostCollection $originals,
        UserId $viewer,
        array $mediaByPost,
        MediaDtoCollection $urls,
    ): array {
        if ($originals->isEmpty()) {
            return [];
        }

        $authors = $this->authorViews($originals);
        $likedPostIds = $this->likedPostIds(posts: $originals, viewer: $viewer);
        $tagsByPost = $this->tagCollectionsByPost($this->postIds($originals));
        $tagDtos = $this->tagsByIds(...\array_values($tagsByPost));

        return $originals->toBase()
            ->map(function (Post $original) use ($authors, $likedPostIds, $mediaByPost, $urls, $tagsByPost, $tagDtos): PostView {
                $originalId = $original->id->value();

                return $this->build(
                    post: $original,
                    author: $this->requireAuthor(authors: $authors, userId: $original->userId->value()),
                    likedByMe: isset($likedPostIds[$originalId]),
                    media: $this->mediaItems(
                        postMedia: $mediaByPost[$originalId] ?? new PostMediaCollection(),
                        urls: $urls,
                    ),
                    tags: $this->tagViews(postTags: $tagsByPost[$originalId] ?? new PostTagCollection(), tags: $tagDtos),
                    original: null,
                );
            })
            ->keyBy(static fn(PostView $view): string => $view->id)
            ->all();
    }
}
