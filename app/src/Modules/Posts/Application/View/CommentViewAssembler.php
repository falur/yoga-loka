<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\User\Public\Contract\UserContract;
use App\Modules\Posts\Domain\Exception\PostAuthorNotFoundException;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Собирает read-model CommentView из доменного комментария: автор — через User, флаг likedByMe и
 * счётчики — из своего модуля. В листингах авторы и флаги likedByMe собираются пакетно (без N+1).
 */
final readonly class CommentViewAssembler
{
    public function __construct(
        private UserContract $users,
        private CommentRepository $commentRepository,
    ) {}

    public function fromComment(Comment $comment, UserId $viewer): CommentView
    {
        return $this->build(
            comment: $comment,
            author: $this->authorView($comment->userId),
            likedByMe: $this->commentRepository->existsLikeByCommentAndUser(commentId: $comment->id, userId: $viewer),
        );
    }

    public function fromComments(CommentCollection $comments, UserId $viewer): CommentViewCollection
    {
        if ($comments->isEmpty()) {
            return new CommentViewCollection();
        }

        $authors = $this->authorViews($comments);
        $likedCommentIds = $this->likedCommentIds(comments: $comments, viewer: $viewer);

        return new CommentViewCollection(
            $comments->toBase()->map(fn(Comment $comment): CommentView => $this->build(
                comment: $comment,
                author: $this->requireAuthor(authors: $authors, userId: $comment->userId->value()),
                likedByMe: isset($likedCommentIds[$comment->id->value()]),
            )),
        );
    }

    private function build(Comment $comment, AuthorView $author, bool $likedByMe): CommentView
    {
        return new CommentView(
            id: $comment->id->value(),
            postId: $comment->postId->value(),
            parentId: $comment->parent->value(),
            text: $comment->text->value(),
            likesCount: $comment->likesCount->value(),
            repliesCount: $comment->repliesCount->value(),
            likedByMe: $likedByMe,
            createdAt: $comment->createdAt,
            author: $author,
        );
    }

    private function authorView(UserId $userId): AuthorView
    {
        return AuthorView::fromProfile($this->users->profile($userId->value()));
    }

    /**
     * @return array<string, AuthorView>
     */
    private function authorViews(CommentCollection $comments): array
    {
        $userIds = \array_values($comments
            ->toBase()
            ->map(static fn(Comment $comment): string => $comment->userId->value())
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
     * (fromComment -> UserContract::profile), а не неконтролируемым undefined array key -> 500.
     * Ключ перевода свой: текст совпадает с текстом владельца, но чужими ключами Posts не бросает.
     *
     * @param array<string, AuthorView> $authors
     */
    private function requireAuthor(array $authors, string $userId): AuthorView
    {
        return $authors[$userId] ?? throw new PostAuthorNotFoundException();
    }

    /**
     * @return array<string, true>
     */
    private function likedCommentIds(CommentCollection $comments, UserId $viewer): array
    {
        $commentIds = $comments->mapToList(static fn(Comment $comment): CommentId => $comment->id);
        $liked = [];

        foreach ($this->commentRepository->findLikesByUserAndCommentIds($viewer, ...$commentIds) as $like) {
            $liked[$like->commentId->value()] = true;
        }

        return $liked;
    }
}
