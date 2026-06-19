<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Posts\Notification;

use App\Modules\Posts\Application\Notification\NotificationContentBuilder;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Tests\TestCase;

/**
 * Сборщик содержимого уведомлений Posts рендерит непустые заголовок и тело в локали получателя
 * (ru и en дают разный текст), корректно собирает deep-link action и снимок автора; пустой
 * avatarUrl недопустим (его непустоту гарантирует профиль автора).
 */
final class NotificationContentBuilderTest extends TestCase
{
    public function testBuildsNonEmptyTranslatedContentForEveryTypeInBothLocales(): void
    {
        $builder = $this->builder();
        $actorId = UserId::generate();

        foreach (PostNotificationType::cases() as $type) {
            $russian = $builder->build(
                type: $type,
                actorUserId: $actorId,
                actorName: 'Иван',
                actorAvatarUrl: 'https://cdn.example/avatar.jpg',
                recipientLocale: 'ru',
                actionType: 'post',
                actionId: $actorId->value(),
            );
            $english = $builder->build(
                type: $type,
                actorUserId: $actorId,
                actorName: 'Иван',
                actorAvatarUrl: 'https://cdn.example/avatar.jpg',
                recipientLocale: 'en',
                actionType: 'post',
                actionId: $actorId->value(),
            );

            self::assertNotSame('', $russian->title->value());
            self::assertNotSame('', $russian->body->value());
            self::assertNotSame(
                $russian->body->value(),
                $english->body->value(),
                \sprintf('Тело уведомления %s не переведено в локали получателя.', $type->value),
            );
            self::assertStringContainsString('Иван', $russian->body->value());
        }
    }

    public function testAssemblesActionAndActor(): void
    {
        $actorId = UserId::generate();
        $commentId = UserId::generate()->value();

        $content = $this->builder()->build(
            type: PostNotificationType::CommentReply,
            actorUserId: $actorId,
            actorName: 'Мария',
            actorAvatarUrl: 'https://cdn.example/m.jpg',
            recipientLocale: 'ru',
            actionType: 'comment',
            actionId: $commentId,
        );

        self::assertSame(
            ['actionType' => 'comment', 'actionId' => $commentId],
            $content->action->jsonSerialize(),
        );
        self::assertSame(
            ['id' => $actorId->value(), 'name' => 'Мария', 'avatarUrl' => 'https://cdn.example/m.jpg'],
            $content->actor->jsonSerialize(),
        );
    }

    public function testRejectsEmptyAvatarUrl(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->builder()->build(
            type: PostNotificationType::PostLike,
            actorUserId: UserId::generate(),
            actorName: 'Иван',
            actorAvatarUrl: '',
            recipientLocale: 'ru',
            actionType: 'post',
            actionId: UserId::generate()->value(),
        );
    }

    private function builder(): NotificationContentBuilder
    {
        return $this->getContainer()->get(NotificationContentBuilder::class);
    }
}
