<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Unit\Domain;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxAvailableAt;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventDate;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\TestCase;

final class OutboxValueObjectTest extends TestCase
{
    public function testOutboxEventTypeAcceptsOutboxMessageClass(): void
    {
        $type = OutboxEventType::fromString(OutboxValueObjectTestMessage::class);

        self::assertSame(OutboxValueObjectTestMessage::class, $type->value());
        self::assertSame(OutboxValueObjectTestMessage::class, (string) $type);
        self::assertSame(OutboxValueObjectTestMessage::class, $type->jsonSerialize());
        self::assertFalse($type->equals(OutboxEventType::fromString(OutboxValueObjectOtherTestMessage::class)));
    }

    public function testOutboxEventTypeRejectsEmptyType(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        OutboxEventType::fromString('');
    }

    public function testOutboxEventTypeRejectsMissingClassType(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        OutboxEventType::fromString('Old\\Removed\\Message');
    }

    public function testOutboxEventPayloadRequiresValidJson(): void
    {
        $payload = OutboxEventPayload::fromJson('{"text":"ok"}');

        self::assertSame('{"text":"ok"}', $payload->value());
        self::assertSame('{"text":"ok"}', (string) $payload);
        self::assertSame('{"text":"ok"}', $payload->jsonSerialize());
        self::assertTrue($payload->equals(OutboxEventPayload::fromJson('{"text":"ok"}')));

        $this->expectException(InvalidDomainValueException::class);

        OutboxEventPayload::fromJson('{broken');
    }

    public function testAttemptsAndBatchSizeAreValidated(): void
    {
        $outboxMaxAttempts = OutboxMaxAttempts::fromInt(100);

        self::assertSame(0, OutboxAttempts::zero()->value());
        self::assertSame(100, $outboxMaxAttempts->value());
        self::assertTrue(OutboxAttempts::zero()->canIncrement($outboxMaxAttempts));
        self::assertTrue(OutboxAttempts::fromInt(99)->canIncrement($outboxMaxAttempts));
        self::assertTrue(OutboxAttempts::fromInt(99)->isLastAllowed($outboxMaxAttempts));
        self::assertFalse(OutboxAttempts::fromInt(100)->canIncrement($outboxMaxAttempts));
        self::assertTrue(OutboxAttempts::fromInt(100)->isLastAllowed($outboxMaxAttempts));
        self::assertSame(1, OutboxAttempts::zero()->increment()->value());
        self::assertFalse(OutboxAttempts::fromInt(1)->equals(OutboxAttempts::fromInt(2)));
        self::assertTrue(OutboxAttempts::fromInt(2)->equals(OutboxAttempts::fromInt(2)));
        self::assertSame(100, OutboxRelayBatchSize::fromInt(100)->value());

        $this->expectException(InvalidDomainValueException::class);

        OutboxRelayBatchSize::fromInt(0);
    }

    public function testRelaySleepSecondsRejectsBusySpinZero(): void
    {
        self::assertSame(1, OutboxRelaySleepSeconds::fromInt(1)->value());
        self::assertSame(30, OutboxRelaySleepSeconds::fromInt(30)->value());

        $this->expectException(InvalidDomainValueException::class);

        OutboxRelaySleepSeconds::fromInt(0);
    }

    public function testAttemptsCannotIncrementPastMaximumInteger(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Количество попыток outbox превышено.');

        OutboxAttempts::fromInt(\PHP_INT_MAX)->increment();
    }

    public function testOutboxDateAndLastErrorHaveEmptyState(): void
    {
        $now = new \DateTimeImmutable('2026-05-25 15:37:00');

        self::assertTrue(OutboxEventDate::none()->isEmpty());
        self::assertNull(OutboxEventDate::none()->value());
        self::assertTrue(OutboxEventDate::none()->equals(OutboxEventDate::none()));
        self::assertTrue(OutboxEventDate::fromDateTime($now)->equals(OutboxEventDate::fromDateTime($now)));
        self::assertFalse(OutboxEventDate::none()->equals(OutboxEventDate::fromDateTime($now)));
        self::assertSame('', OutboxEventDate::none()->jsonSerialize());
        self::assertSame('', (string) OutboxEventDate::none());
        self::assertSame(OutboxEventDate::none()->jsonSerialize(), (string) OutboxEventDate::none());
        $knownOutboxEventDate = OutboxEventDate::fromDateTime($now);
        self::assertFalse($knownOutboxEventDate->isEmpty());
        self::assertSame($now, $knownOutboxEventDate->value());
        self::assertFalse($knownOutboxEventDate->equals(OutboxEventDate::none()));
        self::assertSame($now->format(\DateTimeInterface::ATOM), OutboxEventDate::fromDateTime($now)->jsonSerialize());
        self::assertSame($now->format(\DateTimeInterface::ATOM), (string) OutboxEventDate::fromDateTime($now));
        self::assertSame(
            OutboxEventDate::fromDateTime($now)->jsonSerialize(),
            (string) OutboxEventDate::fromDateTime($now),
        );
        self::assertTrue(OutboxLastError::none()->isEmpty());
        self::assertSame('Ошибка доставки', OutboxLastError::fromString('Ошибка доставки')->value());
        self::assertSame('Ошибка доставки', (string) OutboxLastError::fromString('Ошибка доставки'));
        self::assertSame('Ошибка доставки', OutboxLastError::fromString('Ошибка доставки')->jsonSerialize());
        self::assertTrue(OutboxLastError::fromString('Ошибка доставки')->equals(OutboxLastError::fromString('Ошибка доставки')));
        self::assertSame('', OutboxLastError::none()->value());
        self::assertNull(OutboxLastError::none()->toDatabaseValue());
        self::assertSame('Ошибка доставки', OutboxLastError::fromString('Ошибка доставки')->toDatabaseValue());
        self::assertLessThanOrEqual(
            2000,
            \mb_strlen(OutboxLastError::fromThrowable(new \RuntimeException(\str_repeat('x', 3000)))->value()),
        );
    }

    public function testOutboxLastErrorRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Ошибка outbox имеет неверную длину.');

        OutboxLastError::fromString('');
    }

    public function testOutboxAvailableAtWrapsAndComparesDate(): void
    {
        $now = new \DateTimeImmutable('2026-05-25 15:37:00.123456');
        $outboxAvailableAt = OutboxAvailableAt::fromDateTime($now);

        self::assertSame($now, $outboxAvailableAt->value());
        self::assertSame($now->format(\DateTimeInterface::ATOM), $outboxAvailableAt->jsonSerialize());
        self::assertSame($now->format(\DateTimeInterface::ATOM), (string) $outboxAvailableAt);
        self::assertSame($outboxAvailableAt->jsonSerialize(), (string) $outboxAvailableAt);
        self::assertTrue($outboxAvailableAt->equals(OutboxAvailableAt::fromDateTime($now)));
        self::assertFalse($outboxAvailableAt->equals(OutboxAvailableAt::fromDateTime($now->modify('+1 second'))));
    }

    public function testStoredOutboxEventDoesNotIncrementAttemptsPastMaximum(): void
    {
        $now = new \DateTimeImmutable('2026-05-25T16:06:00+00:00');
        $storedOutboxEvent = StoredOutboxEvent::createAvailableAt(
            type: OutboxEventType::fromString(OutboxValueObjectTestMessage::class),
            payload: OutboxEventPayload::fromJson('{"text":"ok"}'),
            availableAt: $now,
            now: $now,
        );
        $storedOutboxEvent->recordPublishFailure(
            lastError: OutboxLastError::fromString('Первая ошибка.'),
            outboxMaxAttempts: OutboxMaxAttempts::fromInt(1),
            availableAt: $now,
            now: $now,
        );

        $storedOutboxEvent->markFailed(
            lastError: OutboxLastError::fromString('Финальная ошибка.'),
            outboxMaxAttempts: OutboxMaxAttempts::fromInt(1),
            now: $now,
        );

        self::assertSame(1, $storedOutboxEvent->attempts->value());
        self::assertTrue($storedOutboxEvent->isFinal());
    }


    public function testFinalStatusesAreKnown(): void
    {
        self::assertTrue(OutboxEventStatus::Handled->isFinal());
        self::assertTrue(OutboxEventStatus::Failed->isFinal());
        self::assertFalse(OutboxEventStatus::Pending->isFinal());
        self::assertFalse(OutboxEventStatus::Publishing->isFinal());
        self::assertFalse(OutboxEventStatus::Queued->isFinal());
    }
}

final readonly class OutboxValueObjectTestMessage implements IntegrationEvent
{
    public function __construct(
        public string $text,
    ) {}
}

final readonly class OutboxValueObjectOtherTestMessage implements IntegrationEvent
{
    public function __construct(
        public string $text,
    ) {}
}
