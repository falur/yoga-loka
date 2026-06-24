<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Repository\CommentLikeRepository;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Собирает read-model CommentView из доменного комментария: автор — через User, флаг likedByMe и
 * счётчики — из своего модуля. В листингах авторы и флаги likedByMe собираются пакетно (без N+1).
 */
final readonly class CommentViewAssembler
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
        private CommentLikeRepository $commentLikeRepository,
    ) {}

    public function fromComment(Comment $comment, UserId $viewer): CommentView
    {
        return $this->build(
            comment: $comment,
            author: $this->authorView($comment->userId),
            likedByMe: $this->commentLikeRepository->existsByCommentAndUser(commentId: $comment->id, userId: $viewer),
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
            createdAt: $comment->createdAt->format(\DateTimeInterface::ATOM),
            author: $author,
        );
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
     * @return array<string, AuthorView>
     */
    private function authorViews(CommentCollection $comments): array
    {
        $userIds = \array_values($comments
            ->toBase()
            ->map(static fn(Comment $comment): string => $comment->userId->value())
            ->unique()
            ->all());

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
     * (fromComment -> GetUserPublicProfile), а не неконтролируемым undefined array key -> 500.
     *
     * @param array<string, AuthorView> $authors
     */
    private function requireAuthor(array $authors, string $userId): AuthorView
    {
        return $authors[$userId] ?? throw new NotFoundException('app.user.not_found');
    }

    /**
     * @return array<string, true>
     */
    private function likedCommentIds(CommentCollection $comments, UserId $viewer): array
    {
        $commentIds = $comments->mapToList(static fn(Comment $comment): CommentId => $comment->id);
        $liked = [];

        foreach ($this->commentLikeRepository->findByUserAndCommentIds($viewer, ...$commentIds) as $like) {
            $liked[$like->commentId->value()] = true;
        }

        return $liked;
    }
}
