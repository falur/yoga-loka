<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class CommentHttpTest extends PostsHttpTestCase
{
    public function testCommentsOnPostAndNotifiesAuthor(): void
    {
        $author = $this->createUser();
        $commenter = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $response = $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/comments', $post->id->value()),
            $commenter->id,
            ['text' => 'Хороший пост'],
        );

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame('Хороший пост', $data['text']);
        self::assertNull($data['parentId']);
        self::assertSame($commenter->id->value(), $data['author']['userId']);

        self::assertSame(1, $this->reloadPost($post)->commentsCount->value());

        $commented = $this->stagedNotifications('posts.post_commented');
        self::assertCount(1, $commented);
        self::assertSame($author->id->value(), $commented[0]->userId);
    }

    public function testCommentStagesMentionNotifications(): void
    {
        $author = $this->createUser();
        $commenter = $this->createUser();
        $mentioned = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/comments', $post->id->value()),
            $commenter->id,
            ['text' => 'Привет', 'mentions' => [$mentioned->id->value()]],
        )->assertOk();

        $mentions = $this->stagedNotifications('posts.comment_mention');
        self::assertCount(1, $mentions);
        self::assertSame($mentioned->id->value(), $mentions[0]->userId);
        self::assertCount(1, $this->stagedNotifications('posts.post_commented'));
    }

    public function testMentionDedupBeatsPostCommentedForAuthor(): void
    {
        $author = $this->createUser();
        $commenter = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        // Автор записи упомянут в комментарии к своей записи -> получает один comment_mention, не post_commented.
        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/comments', $post->id->value()),
            $commenter->id,
            ['text' => 'Эй', 'mentions' => [$author->id->value()]],
        )->assertOk();

        self::assertCount(0, $this->stagedNotifications('posts.post_commented'));
        $mentions = $this->stagedNotifications('posts.comment_mention');
        self::assertCount(1, $mentions);
        self::assertSame($author->id->value(), $mentions[0]->userId);
    }

    public function testSelfCommentDoesNotNotify(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/comments', $post->id->value()),
            $user->id,
            ['text' => 'Свой комментарий'],
        )->assertOk();

        self::assertCount(0, $this->stagedNotifications('posts.post_commented'));
        self::assertSame(1, $this->reloadPost($post)->commentsCount->value());
    }

    public function testCommentOnDraftReturns404(): void
    {
        $author = $this->createUser();
        $commenter = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Draft);

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/comments', $post->id->value()),
            $commenter->id,
            ['text' => 'Текст'],
        )->assertNotFound();
    }

    public function testRejectsEmptyCommentText(): void
    {
        $author = $this->createUser();
        $commenter = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/comments', $post->id->value()),
            $commenter->id,
            ['text' => ''],
        )->assertUnprocessable();
    }

    public function testRepliesToCommentNotifiesParentAuthorOnly(): void
    {
        $postAuthor = $this->createUser();
        $parentAuthor = $this->createUser();
        $replier = $this->createUser();
        $post = $this->persistPost($postAuthor->id, PostStatus::Published);
        $parent = $this->persistComment($parentAuthor->id, $post);

        $response = $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()),
            $replier->id,
            ['text' => 'Согласен'],
        );

        $response->assertOk();
        self::assertSame($parent->id->value(), $this->json($response)['data']['parentId']);

        self::assertSame(1, $this->reloadComment($parent)->repliesCount->value());

        $replies = $this->stagedNotifications('posts.comment_reply');
        self::assertCount(1, $replies);
        self::assertSame($parentAuthor->id->value(), $replies[0]->userId);

        // Автор записи на ответ post_commented НЕ получает (решение 14).
        self::assertCount(0, $this->stagedNotifications('posts.post_commented'));
    }

    public function testReplyToDeletedCommentReturns404(): void
    {
        $author = $this->createUser();
        $replier = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $parent = $this->persistComment($author->id, $post);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $parent->id->value()), $author->id)
            ->assertNoContent();

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()),
            $replier->id,
            ['text' => 'Ответ'],
        )->assertNotFound();
    }

    public function testDeletesTopLevelCommentDecrementsPost(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        self::assertSame(1, $this->reloadPost($post)->commentsCount->value());

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)
            ->assertNoContent();

        self::assertSame(0, $this->reloadPost($post)->commentsCount->value());
        self::assertTrue($this->reloadComment($comment)->isDeleted());
    }

    public function testDeletesReplyDecrementsParent(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $parent = $this->persistComment($author->id, $post);
        $reply = $this->persistComment($author->id, $post, $parent);

        self::assertSame(1, $this->reloadComment($parent)->repliesCount->value());

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $reply->id->value()), $author->id)
            ->assertNoContent();

        self::assertSame(0, $this->reloadComment($parent)->repliesCount->value());
    }

    public function testRepeatedDeleteIsIdempotent(): void
    {
        $author = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)->assertNoContent();
        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)->assertNoContent();

        self::assertSame(0, $this->reloadPost($post)->commentsCount->value());
    }

    public function testRejectsDeleteByNonOwner(): void
    {
        $author = $this->createUser();
        $stranger = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);
        $comment = $this->persistComment($author->id, $post);

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $stranger->id)
            ->assertStatus(403);
    }

    public function testRejectsDeleteOfMissingComment(): void
    {
        $user = $this->createUser();

        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsInvalidCommentId(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts/comments/not-a-uuid/replies', $user->id, ['text' => 'x'])
            ->assertUnprocessable();
    }

    public function testCommentOnMissingPostReturns404(): void
    {
        $user = $this->createUser();

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/comments', UserId::generate()->value()),
            $user->id,
            ['text' => 'Текст'],
        )->assertNotFound();
    }

    public function testRejectsNonexistentMentionInComment(): void
    {
        $author = $this->createUser();
        $commenter = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Published);

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/%s/comments', $post->id->value()),
            $commenter->id,
            ['text' => 'Текст', 'mentions' => [UserId::generate()->value()]],
        )->assertUnprocessable();
    }

    public function testReplyToMissingCommentReturns404(): void
    {
        $user = $this->createUser();

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/comments/%s/replies', UserId::generate()->value()),
            $user->id,
            ['text' => 'Ответ'],
        )->assertNotFound();
    }

    public function testReplyOnBlockedPostReturns404(): void
    {
        $author = $this->createUser();
        $replier = $this->createUser();
        $post = $this->persistPost($author->id, PostStatus::Blocked);
        $parent = $this->persistComment($author->id, $post);

        $this->authedJson(
            'POST',
            \sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()),
            $replier->id,
            ['text' => 'Ответ'],
        )->assertNotFound();
    }
}
