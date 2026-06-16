<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Domain\ValueObject;

use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionId;
use App\Modules\Notifications\Domain\ValueObject\NotificationActionType;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationChannelDefaults;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationReadState;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class NotificationValueObjectTest extends TestCase
{
    public function testIdentifierValidatesUuidV7(): void
    {
        $generated = NotificationId::generate();

        self::assertTrue($generated->equals(NotificationId::fromString((string) $generated)));

        $this->expectException(InvalidDomainValueException::class);

        NotificationId::fromString(Uuid::uuid4()->toString());
    }

    public function testTypeCodeAcceptsModuleActionFormat(): void
    {
        $type = NotificationTypeCode::fromString('chat.message_received');

        self::assertSame('chat.message_received', $type->value());
        self::assertSame('chat.message_received', (string) $type);
        self::assertSame('chat.message_received', $type->jsonSerialize());
        self::assertTrue($type->equals(NotificationTypeCode::fromString('chat.message_received')));
        self::assertFalse($type->equals(NotificationTypeCode::fromString('post.commented')));
    }

    public function testTypeCodeTrimsSurroundingWhitespace(): void
    {
        $type = NotificationTypeCode::fromString('  chat.message_received  ');

        self::assertSame('chat.message_received', $type->value());
        self::assertTrue($type->equals(NotificationTypeCode::fromString('chat.message_received')));
    }

    public function testTypeCodeRejectsFormatWithoutModuleSegment(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationTypeCode::fromString('messageReceived');
    }

    public function testTypeCodeRejectsTooLongValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationTypeCode::fromString(\sprintf('chat.%s', \str_repeat('a', 251)));
    }

    public function testTitleTrimsAndValidatesLength(): void
    {
        $title = NotificationTitle::fromString('  Новое сообщение  ');

        self::assertSame('Новое сообщение', $title->value());
        self::assertSame('Новое сообщение', (string) $title);
        self::assertSame('Новое сообщение', $title->jsonSerialize());
        self::assertTrue($title->equals(NotificationTitle::fromString('Новое сообщение')));
        self::assertFalse($title->equals(NotificationTitle::fromString('Другое')));
    }

    public function testTitleRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationTitle::fromString('   ');
    }

    public function testTitleRejectsTooLongValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationTitle::fromString(\str_repeat('a', 256));
    }

    public function testBodyTrimsAndValidates(): void
    {
        $body = NotificationBody::fromString('  Вам пришло сообщение  ');

        self::assertSame('Вам пришло сообщение', $body->value());
        self::assertSame('Вам пришло сообщение', (string) $body);
        self::assertSame('Вам пришло сообщение', $body->jsonSerialize());
        self::assertTrue($body->equals(NotificationBody::fromString('Вам пришло сообщение')));
        self::assertFalse($body->equals(NotificationBody::fromString('Другой текст')));
    }

    public function testBodyRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationBody::fromString('');
    }

    public function testDeviceTokenTrimsAndValidates(): void
    {
        $token = DeviceToken::fromString('  fcm-token-value  ');

        self::assertSame('fcm-token-value', $token->value());
        self::assertSame('fcm-token-value', (string) $token);
        self::assertSame('fcm-token-value', $token->jsonSerialize());
        self::assertTrue($token->equals(DeviceToken::fromString('fcm-token-value')));
        self::assertFalse($token->equals(DeviceToken::fromString('other-token')));
    }

    public function testDeviceTokenRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        DeviceToken::fromString('');
    }

    public function testDeviceTokenRejectsTooLongValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        DeviceToken::fromString(\str_repeat('a', 1025));
    }

    public function testActionTypePresentState(): void
    {
        $actionType = NotificationActionType::of(' chat ');

        self::assertTrue($actionType->isPresent());
        self::assertSame('chat', $actionType->value());
        self::assertSame('chat', $actionType->presentValue());
        self::assertSame('chat', (string) $actionType);
        self::assertSame('chat', $actionType->jsonSerialize());
        self::assertTrue($actionType->equals(NotificationActionType::of('chat')));
        self::assertFalse($actionType->equals(NotificationActionType::none()));
    }

    public function testActionTypeNoneState(): void
    {
        $none = NotificationActionType::none();

        self::assertFalse($none->isPresent());
        self::assertNull($none->value());
        self::assertSame('', (string) $none);
        self::assertNull($none->jsonSerialize());
    }

    public function testActionTypeRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActionType::of('  ');
    }

    public function testActionTypeRejectsTooLongValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActionType::of(\str_repeat('a', 256));
    }

    public function testActionTypePresentValueFailsWhenAbsent(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActionType::none()->presentValue();
    }

    public function testActionIdPresentState(): void
    {
        $actionId = NotificationActionId::of(' 42 ');

        self::assertTrue($actionId->isPresent());
        self::assertSame('42', $actionId->value());
        self::assertSame('42', $actionId->presentValue());
        self::assertSame('42', (string) $actionId);
        self::assertSame('42', $actionId->jsonSerialize());
        self::assertTrue($actionId->equals(NotificationActionId::of('42')));
        self::assertFalse($actionId->equals(NotificationActionId::of('43')));
    }

    public function testActionIdNoneState(): void
    {
        $none = NotificationActionId::none();

        self::assertFalse($none->isPresent());
        self::assertNull($none->value());
        self::assertSame('', (string) $none);
        self::assertNull($none->jsonSerialize());
    }

    public function testActionIdRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActionId::of('');
    }

    public function testActionIdRejectsTooLongValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActionId::of(\str_repeat('a', 256));
    }

    public function testActionIdPresentValueFailsWhenAbsent(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActionId::none()->presentValue();
    }

    public function testActionLinkToBuildsTransition(): void
    {
        $action = NotificationAction::linkTo('chat', '42');

        self::assertTrue($action->hasLink());
        self::assertSame('chat', $action->actionType()->presentValue());
        self::assertSame('42', $action->actionId()->presentValue());
        self::assertSame(['actionType' => 'chat', 'actionId' => '42'], $action->jsonSerialize());
    }

    public function testActionNoneHasNoLink(): void
    {
        $none = NotificationAction::none();

        self::assertFalse($none->hasLink());
        self::assertNull($none->jsonSerialize());
    }

    public function testActionFromPartsNormalizesPartialToNone(): void
    {
        $partial = NotificationAction::fromParts(
            actionType: NotificationActionType::of('chat'),
            actionId: NotificationActionId::none(),
        );

        self::assertFalse($partial->hasLink());
        self::assertNull($partial->jsonSerialize());
    }

    public function testActionFromPartsKeepsFullLink(): void
    {
        $full = NotificationAction::fromParts(
            actionType: NotificationActionType::of('chat'),
            actionId: NotificationActionId::of('42'),
        );

        self::assertTrue($full->hasLink());
        self::assertSame(['actionType' => 'chat', 'actionId' => '42'], $full->jsonSerialize());
    }

    public function testActionEquals(): void
    {
        $action = NotificationAction::linkTo('chat', '42');

        self::assertTrue($action->equals(NotificationAction::linkTo('chat', '42')));
        self::assertFalse($action->equals(NotificationAction::linkTo('chat', '43')));
        self::assertTrue(NotificationAction::none()->equals(NotificationAction::none()));
    }

    public function testActorPresentStateKeepsSnapshot(): void
    {
        $userId = UserId::generate();
        $actor = NotificationActor::of(userId: $userId, name: '  Иван Петров  ', avatarUrl: '  https://cdn/a.jpg  ');

        self::assertTrue($actor->isPresent());
        self::assertSame($userId->value(), $actor->presentId());
        self::assertSame('Иван Петров', $actor->presentName());
        self::assertSame('https://cdn/a.jpg', $actor->presentAvatarUrl());
        self::assertSame(
            ['id' => $userId->value(), 'name' => 'Иван Петров', 'avatarUrl' => 'https://cdn/a.jpg'],
            $actor->jsonSerialize(),
        );
    }

    public function testActorEqualsComparesEveryField(): void
    {
        $userId = UserId::generate();
        $actor = NotificationActor::of(userId: $userId, name: 'Иван', avatarUrl: 'https://cdn/a.jpg');

        self::assertTrue($actor->equals(NotificationActor::of(userId: $userId, name: 'Иван', avatarUrl: 'https://cdn/a.jpg')));
        self::assertFalse($actor->equals(NotificationActor::of(userId: UserId::generate(), name: 'Иван', avatarUrl: 'https://cdn/a.jpg')));
        self::assertFalse($actor->equals(NotificationActor::of(userId: $userId, name: 'Пётр', avatarUrl: 'https://cdn/a.jpg')));
        self::assertFalse($actor->equals(NotificationActor::of(userId: $userId, name: 'Иван', avatarUrl: 'https://cdn/b.jpg')));
        self::assertFalse($actor->equals(NotificationActor::none()));
    }

    public function testActorNoneState(): void
    {
        $none = NotificationActor::none();

        self::assertFalse($none->isPresent());
        self::assertNull($none->jsonSerialize());
        self::assertTrue($none->equals(NotificationActor::none()));
        self::assertFalse($none->equals(NotificationActor::of(userId: UserId::generate(), name: 'Иван', avatarUrl: 'https://cdn/a.jpg')));
    }

    public function testActorPresentIdFailsWhenAbsent(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActor::none()->presentId();
    }

    public function testActorPresentNameFailsWhenAbsent(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActor::none()->presentName();
    }

    public function testActorPresentAvatarUrlFailsWhenAbsent(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActor::none()->presentAvatarUrl();
    }

    public function testActorRejectsEmptyName(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActor::of(userId: UserId::generate(), name: '   ', avatarUrl: 'https://cdn/a.jpg');
    }

    public function testActorRejectsTooLongName(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActor::of(userId: UserId::generate(), name: \str_repeat('a', 256), avatarUrl: 'https://cdn/a.jpg');
    }

    public function testActorRejectsEmptyAvatarUrl(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActor::of(userId: UserId::generate(), name: 'Иван', avatarUrl: '   ');
    }

    public function testActorRejectsTooLongAvatarUrl(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationActor::of(userId: UserId::generate(), name: 'Иван', avatarUrl: \str_repeat('a', 2049));
    }

    public function testReadStateUnread(): void
    {
        $unread = NotificationReadState::unread();

        self::assertFalse($unread->isRead());
        self::assertNull($unread->value());
        self::assertTrue($unread->equals(NotificationReadState::unread()));
    }

    public function testReadStateRead(): void
    {
        $readAt = new \DateTimeImmutable('2026-06-13 12:00:00');
        $read = NotificationReadState::readAt($readAt);

        self::assertTrue($read->isRead());
        self::assertSame($readAt, $read->value());
        self::assertSame($readAt, $read->markedAt());
        self::assertTrue($read->equals(NotificationReadState::readAt($readAt)));
        self::assertFalse($read->equals(NotificationReadState::unread()));
        self::assertFalse($read->equals(NotificationReadState::readAt($readAt->modify('+1 second'))));
    }

    public function testReadStateMarkedAtFailsWhenUnread(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationReadState::unread()->markedAt();
    }

    public function testChannelDefaultsReportEnabledChannels(): void
    {
        $defaults = NotificationChannelDefaults::of(NotificationChannel::Database, NotificationChannel::Push);

        self::assertTrue($defaults->isEnabled(NotificationChannel::Database));
        self::assertTrue($defaults->isEnabled(NotificationChannel::Push));
        self::assertFalse($defaults->isEnabled(NotificationChannel::Realtime));
        self::assertSame(['database', 'push'], $defaults->jsonSerialize());
    }

    public function testChannelDefaultsEqualsIgnoresOrder(): void
    {
        $defaults = NotificationChannelDefaults::of(NotificationChannel::Database, NotificationChannel::Push);

        self::assertTrue(
            $defaults->equals(NotificationChannelDefaults::of(NotificationChannel::Push, NotificationChannel::Database)),
        );
        self::assertFalse($defaults->equals(NotificationChannelDefaults::of(NotificationChannel::Database)));
    }

    public function testChannelDefaultsRejectDuplicateChannel(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        NotificationChannelDefaults::of(NotificationChannel::Push, NotificationChannel::Push);
    }
}
