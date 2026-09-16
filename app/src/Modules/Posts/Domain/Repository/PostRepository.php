<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Repository;

use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Collection\PostLikeCollection;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostMentionCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение записей. Корень агрегата — Post, его внутренние сущности — вложение, тег, упоминание и
 * лайк записи: они создаются только у своей записи и уходят вместе с ней (своего жизненного цикла
 * не имеют), поэтому своего репозитория у них нет и выборку по их таблицам ведёт этот репозиторий.
 */
interface PostRepository
{
    public function findById(PostId $postId): Post|null;

    /**
     * Набор записей по идентификаторам одним запросом — для пакетной сборки оригиналов репостов в
     * листинге без N+1. Soft-deleted не прячется неявно: видимость решает вызывающий через политику.
     */
    public function findByIds(PostId ...$postIds): PostCollection;

    /**
     * Cursor-пагинация по UUID v7 id: id DESC, при наличии курсора — строки строго старше курсора.
     * Фильтр по статусу опционален. Soft-deleted не прячется неявно — это решает вызывающий.
     */
    public function findByUserId(
        UserId $userId,
        PostStatus|null $status,
        PostId|null $cursor,
        int $limit,
    ): PostCollection;

    /**
     * Видимая лента автора: как findByUserId, но скрывает мягко удалённые записи. Статус опционален:
     * вызывающий передаёт Published для чужой ленты и null (все статусы) для своей. excludeStatus
     * исключает один статус из выборки — для своей ленты это Blocked, чтобы заблокированная
     * модерацией запись не была видна никому.
     */
    public function findVisibleByUserId(
        UserId $userId,
        PostStatus|null $status,
        PostStatus|null $excludeStatus,
        PostId|null $cursor,
        int $limit,
    ): PostCollection;

    public function findRepostsOf(PostId $postId): PostCollection;

    /**
     * Вложения записи — внутренняя сущность агрегата, по позиции.
     */
    public function findMediaByPostId(PostId $postId): PostMediaCollection;

    /**
     * Вложения набора записей одним запросом — для сборки листинга ленты без N+1.
     */
    public function findMediaByPostIds(PostId ...$postIds): PostMediaCollection;

    /**
     * Метки записи — внутренняя сущность агрегата.
     */
    public function findTagsByPostId(PostId $postId): PostTagCollection;

    /**
     * Метки набора записей одним запросом — для сборки листинга ленты без N+1.
     */
    public function findTagsByPostIds(PostId ...$postIds): PostTagCollection;

    public function findTagsByTagId(PostTagReference $tagId): PostTagCollection;

    /**
     * Упоминания записи — внутренняя сущность агрегата.
     */
    public function findMentionsByPostId(PostId $postId): PostMentionCollection;

    public function findMentionsByUserId(UserId $userId): PostMentionCollection;

    /**
     * Лайк записи пользователем — внутренняя сущность агрегата.
     */
    public function findLikeByPostAndUser(PostId $postId, UserId $userId): PostLike|null;

    public function existsLikeByPostAndUser(PostId $postId, UserId $userId): bool;

    public function findLikesByUserId(UserId $userId): PostLikeCollection;

    /**
     * Лайки пользователя по набору записей — для флага likedByMe в листингах без N+1.
     */
    public function findLikesByUserAndPostIds(UserId $userId, PostId ...$postIds): PostLikeCollection;

    /**
     * Ставит запись в текущую единицу работы без прогона: она уйдёт в базу тем прогоном, которым
     * Handler завершает запись сценария (своим или другого корня — EntityManager один на запрос).
     * Тем же приёмом, что и `LoginCodeRepository::add()`/`StoredOutboxEventRepository::add()`,
     * сохраняется прежняя граница ровно одного прогона на сценарий с двумя корнями (Post и Comment).
     */
    public function add(Post $post): void;

    public function save(Post $post): void;

    /**
     * Сохраняет запись вместе со вторым независимым экземпляром записи того же агрегата — оригиналом
     * репоста, у которого меняется только счётчик репостов, — одним прогоном EntityManager.
     */
    public function saveWithOriginal(Post $post, Post $original): void;

    /**
     * Сохраняет запись вместе с новым лайком одним прогоном EntityManager: лайк создаётся только у
     * своей записи и собственного репозитория не получает.
     */
    public function saveWithLike(Post $post, PostLike $like): void;

    /**
     * Снимает лайк записи и сохраняет запись одним прогоном EntityManager. Запись передаётся всегда:
     * когда её счётчик лайков не меняется (уже ноль), сущность persist-ится без изменений и лишнего
     * UPDATE не создаёт — так же, как сегодня выбор писать её или нет не влияет на итоговый SQL.
     */
    public function removeLike(PostLike $like, Post $post): void;

    /**
     * Сохраняет новую запись вместе с её вложениями, метками и упоминаниями одним прогоном
     * EntityManager: все они создаются только вместе со своей записью.
     */
    public function saveWithAttachments(
        Post $post,
        PostMediaCollection $media,
        PostTagCollection $tags,
        PostMentionCollection $mentions,
    ): void;

    /**
     * Сохраняет репост (новую запись с её вложениями, метками и упоминаниями) вместе с оригиналом,
     * у которого увеличивается счётчик репостов, одним прогоном EntityManager.
     */
    public function saveRepost(
        Post $post,
        PostMediaCollection $media,
        PostTagCollection $tags,
        PostMentionCollection $mentions,
        Post $original,
    ): void;
}
