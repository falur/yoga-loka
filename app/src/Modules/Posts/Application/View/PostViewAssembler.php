<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
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
use App\Modules\Tags\Application\Dto\TagTextCollection;
use App\Modules\Tags\Application\Query\GetTags\GetTagsHandler;
use App\Modules\Tags\Application\Query\GetTags\GetTagsQuery;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Собирает read-model PostView из доменной записи, обогащая её данными смежных модулей: автор —
 * через User, URL медиа — через Media, тексты тегов — через Tags; флаг likedByMe и счётчики — из
 * своего модуля. Для репоста доглубляет исходную запись на один уровень (без её собственного
 * оригинала), если она видна зрителю.
 *
 * В листингах пакетно собираются выборки из БД: авторы, флаги likedByMe, медиа и теги берутся одним
 * запросом на страницу (без N+1 на уровне БД). Разрешение URL каждого вложения остаётся поэлементным
 * (mediaItem -> FindMediaUrl): пакетного контракта разрешения URL в Media сейчас нет, поэтому число
 * вызовов растёт линейно с числом вложений на странице. Это сознательный компромисс, а не «без N+1»
 * на уровне URL медиа.
 */
final readonly class PostViewAssembler
{
    // TTL ссылки на медиа записи для показа (для приватного медиа — срок presigned-ссылки).
    private const int POST_MEDIA_URL_TTL_SECONDS = 3600;

    public function __construct(
        private QueryBusInterface $queryBus,
        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
        private FindMediaUrlHandler $findMediaUrlHandler,
        private GetTagsHandler $getTagsHandler,
        private PostRepository $postRepository,
        private PostMediaRepository $postMediaRepository,
        private PostTagRepository $postTagRepository,
        private PostLikeRepository $postLikeRepository,
    ) {}

    public function fromPost(Post $post, UserId $viewer): PostView
    {
        $tags = $this->postTagRepository->findByPostId($post->id);

        return $this->build(
            post: $post,
            author: $this->authorView($post->userId),
            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $post->id, userId: $viewer),
            media: $this->mediaItems($this->postMediaRepository->findByPostId($post->id)),
            tags: $this->tagViews(tags: $tags, texts: $this->tagTexts($tags)),
            original: $this->originalView(post: $post, viewer: $viewer),
        );
    }

    /**
     * @return list<PostView>
     */
    public function fromPosts(PostCollection $posts, UserId $viewer): array
    {
        $entities = $posts->all();

        if ($entities === []) {
            return [];
        }

        $postIds = $this->postIds($entities);
        $authors = $this->authorViews($entities);
        $likedPostIds = $this->likedPostIds(posts: $entities, viewer: $viewer);
        $mediaByPost = $this->mediaCollectionsByPost($postIds);
        $tagsByPost = $this->tagCollectionsByPost($postIds);
        $tagTexts = $this->tagTexts(...\array_values($tagsByPost));
        $originals = $this->originalViewsByPost(posts: $entities, viewer: $viewer);

        $views = [];

        foreach ($entities as $post) {
            $postId = $post->id->value();
            $originalId = $post->original->value();
            $views[] = $this->build(
                post: $post,
                author: $this->requireAuthor(authors: $authors, userId: $post->userId->value()),
                likedByMe: isset($likedPostIds[$postId]),
                media: $this->mediaItems($mediaByPost[$postId] ?? new PostMediaCollection()),
                tags: $this->tagViews(tags: $tagsByPost[$postId] ?? new PostTagCollection(), texts: $tagTexts),
                original: $originalId !== null ? ($originals[$originalId] ?? null) : null,
            );
        }

        return $views;
    }

    /**
     * @param list<PostMediaItemView> $media
     * @param list<TagView> $tags
     */
    private function build(Post $post, AuthorView $author, bool $likedByMe, array $media, array $tags, PostView|null $original): PostView
    {
        return new PostView(
            id: $post->id->value(),
            text: $post->text->value(),
            status: $post->status->value,
            attachmentType: $this->attachmentType(post: $post, media: $media)->value,
            likesCount: $post->likesCount->value(),
            repostsCount: $post->repostsCount->value(),
            commentsCount: $post->commentsCount->value(),
            likedByMe: $likedByMe,
            createdAt: $post->createdAt->format(\DateTimeInterface::ATOM),
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
     * @param list<PostMediaItemView> $media
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
        $profile = $this->queryBus->dispatch(
            query: new GetUserPublicProfileQuery($userId->value()),
            handler: $this->getUserPublicProfileHandler->handle(...),
        );

        return new AuthorView(userId: $profile->userId, name: $profile->name, avatarUrl: $profile->avatarUrl);
    }

    /**
     * @param array<int, Post> $posts
     *
     * @return array<string, AuthorView>
     */
    private function authorViews(array $posts): array
    {
        $userIds = \array_values(\array_unique(\array_map(
            static fn(Post $post): string => $post->userId->value(),
            $posts,
        )));

        $profiles = $this->queryBus->dispatch(
            query: new GetUserPublicProfilesQuery($userIds),
            handler: $this->getUserPublicProfilesHandler->handle(...),
        );

        $authors = [];

        foreach ($profiles as $profile) {
            $authors[$profile->userId] = new AuthorView(
                userId: $profile->userId,
                name: $profile->name,
                avatarUrl: $profile->avatarUrl,
            );
        }

        return $authors;
    }

    /**
     * Берёт автора из пакетной карты профилей. GetUserPublicProfiles молча опускает отсутствующих,
     * поэтому отсутствие ключа обрабатываем явно — той же 404, что и одиночный путь
     * (fromPost -> GetUserPublicProfile), а не неконтролируемым undefined array key -> 500.
     *
     * @param array<string, AuthorView> $authors
     */
    private function requireAuthor(array $authors, string $userId): AuthorView
    {
        return $authors[$userId] ?? throw new NotFoundException('app.user.not_found');
    }

    /**
     * @param array<int, Post> $posts
     *
     * @return array<string, true>
     */
    private function likedPostIds(array $posts, UserId $viewer): array
    {
        $postIds = \array_map(static fn(Post $post): PostId => $post->id, $posts);
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
     * @return list<PostMediaItemView>
     */
    private function mediaItems(PostMediaCollection $media): array
    {
        $items = [];

        foreach ($media as $postMedia) {
            $item = $this->mediaItem($postMedia);

            if ($item === null) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Разрешает ссылку на медиа записи без бросающего GetMediaUrl: если медиа удалено или ещё не
     * готово, недоступное вложение исключается из ответа, а не роняет чтение записи/ленты в 500.
     *
     * FindMediaUrl вызывается напрямую (минуя QueryBus), как и в UserPublicProfileAssembler: это
     * единственный сценарий Media с nullable-результатом, а обёртка шины теряет null из вывода типов.
     * Прямой вызов сохраняет контракт «медиа недоступно -> null -> вложение пропущено» без try-catch
     * и без подавления статанализа.
     */
    private function mediaItem(PostMedia $postMedia): PostMediaItemView|null
    {
        $mediaUrl = $this->findMediaUrlHandler->handle(
            new FindMediaUrlQuery(
                mediaId: $postMedia->media->value(),
                presignedTtlSeconds: self::POST_MEDIA_URL_TTL_SECONDS,
            ),
        );

        if ($mediaUrl === null) {
            return null;
        }

        return new PostMediaItemView(
            mediaId: $postMedia->media->value(),
            url: $mediaUrl->url,
            position: $postMedia->position->value(),
        );
    }

    /**
     * Тексты тегов набора связей одним запросом к Tags. Несколько коллекций (по записи в листинге)
     * собираются в один батч, чтобы не ходить в Tags на каждую запись.
     */
    private function tagTexts(PostTagCollection ...$tagCollections): TagTextCollection
    {
        $tagIds = [];

        foreach ($tagCollections as $tags) {
            foreach ($tags as $postTag) {
                $tagIds[] = $postTag->tagId->value();
            }
        }

        if ($tagIds === []) {
            return new TagTextCollection();
        }

        return $this->queryBus->dispatch(
            query: new GetTagsQuery(\array_values(\array_unique($tagIds))),
            handler: $this->getTagsHandler->handle(...),
        );
    }

    /**
     * Строит представления тегов записи из общего батча текстов. В листинге батч содержит теги всех
     * записей страницы, поэтому теги, не относящиеся к этой записи, отсеиваются по принадлежности.
     * Принадлежность проверяется по заранее построенному множеству id связей записи за O(1) (вместо
     * линейного поиска по связям на каждый элемент батча) — это убирает квадратичность на странице с
     * тег-тяжёлыми записями. Порядок тегов в ответе определяется выборкой текстов из Tags и не
     * является частью контракта (в post_tags нет колонки позиции — порядок добавления не хранится).
     *
     * @return list<TagView>
     */
    private function tagViews(PostTagCollection $tags, TagTextCollection $texts): array
    {
        $postTagIds = [];

        foreach ($tags as $postTag) {
            $postTagIds[$postTag->tagId->value()] = true;
        }

        $views = [];

        foreach ($texts as $tagId => $text) {
            if (!isset($postTagIds[$tagId])) {
                continue;
            }

            $views[] = new TagView(id: $tagId, text: $text);
        }

        return $views;
    }

    /**
     * @param array<int, Post> $posts
     *
     * @return list<PostId>
     */
    private function postIds(array $posts): array
    {
        return \array_values(\array_map(static fn(Post $post): PostId => $post->id, $posts));
    }

    private function originalView(Post $post, UserId $viewer): PostView|null
    {
        $originalId = $post->original->value();

        if ($originalId === null) {
            return null;
        }

        $original = $this->postRepository->findById(PostId::fromString($originalId));

        if ($original === null || !PostVisibilityPolicy::isVisibleTo(post: $original, viewer: $viewer)) {
            return null;
        }

        $tags = $this->postTagRepository->findByPostId($original->id);

        return $this->build(
            post: $original,
            author: $this->authorView($original->userId),
            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $original->id, userId: $viewer),
            media: $this->mediaItems($this->postMediaRepository->findByPostId($original->id)),
            tags: $this->tagViews(tags: $tags, texts: $this->tagTexts($tags)),
            original: null,
        );
    }

    /**
     * Обогащает оригиналы репостов страницы одним пакетом (как и сами записи): один запрос за
     * оригиналами, их авторами, медиа, тегами и флагами likedByMe. Без этого каждый репост дочитывал
     * бы свой оригинал по отдельности (N+1 на уровень глубже). Невидимый зрителю оригинал в карту не
     * попадает — у репоста original будет null.
     *
     * @param array<int, Post> $posts
     *
     * @return array<string, PostView>
     */
    private function originalViewsByPost(array $posts, UserId $viewer): array
    {
        $originalIds = [];

        foreach ($posts as $post) {
            $originalId = $post->original->value();

            if ($originalId !== null) {
                $originalIds[$originalId] = PostId::fromString($originalId);
            }
        }

        if ($originalIds === []) {
            return [];
        }

        $originals = $this->postRepository->findByIds(...\array_values($originalIds))->filter(
            static fn(Post $original): bool => PostVisibilityPolicy::isVisibleTo(post: $original, viewer: $viewer),
        )->values();

        if ($originals->isEmpty()) {
            return [];
        }

        $entities = $originals->all();
        $originalPostIds = $this->postIds($entities);
        $authors = $this->authorViews($entities);
        $likedPostIds = $this->likedPostIds(posts: $entities, viewer: $viewer);
        $mediaByPost = $this->mediaCollectionsByPost($originalPostIds);
        $tagsByPost = $this->tagCollectionsByPost($originalPostIds);
        $tagTexts = $this->tagTexts(...\array_values($tagsByPost));

        $views = [];

        foreach ($entities as $original) {
            $originalId = $original->id->value();
            $views[$originalId] = $this->build(
                post: $original,
                author: $this->requireAuthor(authors: $authors, userId: $original->userId->value()),
                likedByMe: isset($likedPostIds[$originalId]),
                media: $this->mediaItems($mediaByPost[$originalId] ?? new PostMediaCollection()),
                tags: $this->tagViews(tags: $tagsByPost[$originalId] ?? new PostTagCollection(), texts: $tagTexts),
                original: null,
            );
        }

        return $views;
    }
}
