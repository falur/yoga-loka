<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Repository;

use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Collection\CommentLikeCollection;
use App\Modules\Posts\Domain\Collection\CommentMentionCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение комментариев. Корень агрегата — Comment, его внутренние сущности — лайк и упоминание
 * комментария: они создаются только у своего комментария и уходят вместе с ним (своего жизненного
 * цикла не имеют), поэтому своего репозитория у них нет и выборку по их таблицам ведёт этот
 * репозиторий.
 */
interface CommentRepository
{
    public function findById(CommentId $commentId): Comment|null;

    public function findByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection;

    /**
     * Комментарии верхнего уровня записи (parent_comment_id IS NULL), без удалённых, cursor-пагинация.
     */
    public function findTopLevelByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection;

    public function findReplies(CommentId $parentId, CommentId|null $cursor, int $limit): CommentCollection;

    /**
     * Лайк комментария пользователем — внутренняя сущность агрегата.
     */
    public function findLikeByCommentAndUser(CommentId $commentId, UserId $userId): CommentLike|null;

    public function existsLikeByCommentAndUser(CommentId $commentId, UserId $userId): bool;

    /**
     * Лайки пользователя по набору комментариев — для флага likedByMe в листингах без N+1.
     */
    public function findLikesByUserAndCommentIds(UserId $userId, CommentId ...$commentIds): CommentLikeCollection;

    /**
     * Упоминания комментария — внутренняя сущность агрегата.
     */
    public function findMentionsByCommentId(CommentId $commentId): CommentMentionCollection;

    /**
     * Ставит комментарий в текущую единицу работы без прогона: он уйдёт в базу тем прогоном,
     * которым Handler завершает запись сценария (своим или другого корня — EntityManager один
     * на запрос). Тем же приёмом, что и `LoginCodeRepository::add()`/`StoredOutboxEventRepository::add()`,
     * сохраняется прежняя граница ровно одного прогона на сценарий с двумя корнями (Comment и Post).
     */
    public function add(Comment $comment): void;

    /**
     * Сохраняет комментарий своим прогоном: вместе с ним в базу уходит всё, что уже поставлено
     * в текущую единицу работы — включая переданное через `add()` другим корнем этого же
     * репозитория (второй экземпляр Comment — родитель ответа) или соседним репозиторием.
     */
    public function save(Comment $comment): void;

    /**
     * Сохраняет комментарий вместе с новым лайком одним прогоном EntityManager: лайк создаётся
     * только у своего комментария и собственного репозитория не получает.
     */
    public function saveWithLike(Comment $comment, CommentLike $like): void;

    /**
     * Снимает лайк комментария и сохраняет комментарий одним прогоном EntityManager. Комментарий
     * передаётся всегда: когда его счётчик лайков не меняется (уже ноль), сущность persist-ится без
     * изменений и лишнего UPDATE не создаёт.
     */
    public function removeLike(CommentLike $like, Comment $comment): void;

    /**
     * Ставит новый комментарий (верхнего уровня или ответ) вместе с его упоминаниями в текущую
     * единицу работы без прогона: упоминания создаются только вместе со своим комментарием.
     * Счётчик родителя (для ответа) или записи (для верхнего уровня) — отдельный экземпляр своего
     * корня и сохраняется своим вызовом (`add()`/`save()`) вызывающим Handler — тем же прогоном.
     */
    public function addWithMentions(Comment $comment, CommentMentionCollection $mentions): void;
}
