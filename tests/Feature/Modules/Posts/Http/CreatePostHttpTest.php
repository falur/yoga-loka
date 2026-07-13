<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\ValueObject\UserId;

final class CreatePostHttpTest extends PostsHttpTestCase
{
    public function testCreatesPublishedPost(): void
    {
        $user = $this->createUser();

        $response = $this->authedJson('POST', '/api/v1/posts', $user->id, ['text' => 'Привет мир']);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame('published', $data['status']);
        self::assertSame('Привет мир', $data['text']);
        self::assertSame('none', $data['attachmentType']);
        self::assertFalse($data['likedByMe']);
        self::assertSame(0, $data['likesCount']);
        self::assertSame($user->id->value(), $data['author']['userId']);
        self::assertNull($data['original']);
    }

    public function testCreatesDraftPost(): void
    {
        $user = $this->createUser();

        $response = $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Черновик',
            'draft' => true,
        ]);

        $response->assertOk();
        self::assertSame('draft', $this->json($response)['data']['status']);
    }

    public function testCreatesPostWithMedia(): void
    {
        $user = $this->createUser();
        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);

        $response = $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'mediaIds' => [$media->id->value()],
        ]);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame('media', $data['attachmentType']);
        self::assertCount(1, $data['media']);
        self::assertSame($media->id->value(), $data['media'][0]['id']);
    }

    public function testDeduplicatesRepeatedMediaId(): void
    {
        $user = $this->createUser();
        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);

        $response = $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'mediaIds' => [$media->id->value(), $media->id->value()],
        ]);

        $response->assertOk();
        $data = $this->json($response)['data'];
        self::assertSame('media', $data['attachmentType']);
        self::assertCount(1, $data['media']);
        self::assertSame($media->id->value(), $data['media'][0]['id']);
    }

    public function testRejectsForeignMedia(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $media = $this->createReadyMedia($other->id, MediaVisibility::Public);

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'mediaIds' => [$media->id->value()],
        ])->assertStatus(403);
    }

    public function testRejectsMediaThatIsNotReady(): void
    {
        $user = $this->createUser();
        $media = $this->createUploadedMedia($user->id);

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'mediaIds' => [$media->id->value()],
        ])->assertUnprocessable();
    }

    public function testRejectsMissingMedia(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'mediaIds' => [UserId::generate()->value()],
        ])->assertNotFound();
    }

    public function testCreatesPostWithNewAndExistingTags(): void
    {
        $user = $this->createUser();
        $this->persist(Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id));

        $response = $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'tags' => ['yoga', 'meditation'],
        ]);

        $response->assertOk();
        $tagTexts = \array_column($this->json($response)['data']['tags'], 'text');
        self::assertContains('yoga', $tagTexts);
        self::assertContains('meditation', $tagTexts);
        self::assertCount(2, $tagTexts);
    }

    public function testCreatesPostWithMentionsStagesNotifications(): void
    {
        $author = $this->createUser();
        $mentionedFirst = $this->createUser();
        $mentionedSecond = $this->createUser();

        $response = $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'mentions' => [$mentionedFirst->id->value(), $mentionedSecond->id->value()],
        ]);

        $response->assertOk();

        $mentions = $this->stagedNotifications('posts.post_mention');
        self::assertCount(2, $mentions);

        $recipients = \array_map(static fn(object $message): string => $message->userId, $mentions);
        self::assertContains($mentionedFirst->id->value(), $recipients);
        self::assertContains($mentionedSecond->id->value(), $recipients);
    }

    public function testDraftMentionsArePersistedButNotNotified(): void
    {
        $author = $this->createUser();
        $mentioned = $this->createUser();

        $response = $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Черновик с упоминанием',
            'draft' => true,
            'mentions' => [$mentioned->id->value()],
        ]);

        $response->assertOk();
        $postId = $this->json($response)['data']['id'];

        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));
        self::assertCount(1, $this->postMentionRepository()->findByPostId(PostId::fromString($postId)));
    }

    public function testDoesNotNotifySelfMention(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Сам себя упомянул',
            'mentions' => [$user->id->value()],
        ])->assertOk();

        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));
    }

    public function testRejectsNonexistentMention(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'mentions' => [UserId::generate()->value()],
        ])->assertUnprocessable();
    }

    public function testRejectsNonexistentMentionInDraft(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Черновик с несуществующим упоминанием',
            'draft' => true,
            'mentions' => [UserId::generate()->value()],
        ])->assertUnprocessable();
    }

    public function testRejectsTooManyMentions(): void
    {
        $user = $this->createUser();
        $mentions = [];
        for ($index = 0; $index <= 50; $index++) {
            $mentions[] = UserId::generate()->value();
        }

        $this->authedJson('POST', '/api/v1/posts', $user->id, ['mentions' => $mentions])->assertUnprocessable();
    }

    public function testRejectsTextOverLimit(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => \str_repeat('а', 5001),
        ])->assertUnprocessable();
    }

    public function testRejectsInvalidMediaUuid(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'mediaIds' => ['not-a-uuid'],
        ])->assertUnprocessable();
    }

    public function testRequiresAuthentication(): void
    {
        $response = $this->fakeHttp()->postJson('/api/v1/posts', ['text' => 'Аноним']);

        $response->assertUnauthorized();
    }
}
