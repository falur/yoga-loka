<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Repository;

use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Repository\InvalidOutboxPendingRow;
use App\Modules\Outbox\Repository\OutboxPendingRow;
use App\Modules\Outbox\Repository\ValidOutboxPendingRow;
use PHPUnit\Framework\TestCase;

final class OutboxPendingRowTest extends TestCase
{
    public function testNonStringIdBecomesInvalidRowWithRawId(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['id' => 123]));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertSame('123', $outboxPendingRow->rawOutboxEventId);
        self::assertStringContainsString('Поле `id` должно быть строкой.', $outboxPendingRow->lastError->value());
    }

    public function testMissingIdBecomesInvalidRow(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['id' => null]));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertSame('null', $outboxPendingRow->rawOutboxEventId);
        self::assertStringContainsString('Поле `id` должно быть строкой.', $outboxPendingRow->lastError->value());
    }

    public function testNonScalarIdBecomesInvalidRowWithTypeName(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['id' => ['nested']]));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertSame('array', $outboxPendingRow->rawOutboxEventId);
    }

    public function testRowThatParsesWithoutErrorsBecomesValidMarker(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow([
            'attempts' => '1',
            'queued_at' => new \DateTimeImmutable('2026-05-25T16:06:00+00:00'),
            'last_error' => 'Предыдущая ошибка.',
        ]));

        self::assertInstanceOf(ValidOutboxPendingRow::class, $outboxPendingRow);
    }

    public function testRowWithMutableDateInterfaceParsesAsValid(): void
    {
        // Дата может прийти как \DateTime (не immutable) — парсер должен принять её,
        // приведя к \DateTimeImmutable, и не считать строку повреждённой.
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow([
            'queued_at' => new \DateTime('2026-05-25T16:06:00+00:00'),
        ]));

        self::assertInstanceOf(ValidOutboxPendingRow::class, $outboxPendingRow);
    }

    public function testInvalidRowKeepsRawIdAndError(): void
    {
        $outboxEventId = OutboxEventId::generate()->value();

        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow([
            'id' => $outboxEventId,
            'attempts' => -1,
        ]));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertSame($outboxEventId, $outboxPendingRow->rawOutboxEventId);
        self::assertStringContainsString('Количество попыток outbox', $outboxPendingRow->lastError->value());
    }

    public function testInvalidRowFromNonStringRequiredField(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['type' => 123]));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertStringContainsString('Поле `type` должно быть строкой.', $outboxPendingRow->lastError->value());
    }

    public function testInvalidRowFromNonIntegerAttempts(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['attempts' => 'wrong']));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertStringContainsString('attempts', $outboxPendingRow->lastError->value());
    }

    public function testInvalidRowFromNonNullableDateNull(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['available_at' => null]));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertStringContainsString('не должно быть NULL', $outboxPendingRow->lastError->value());
    }

    public function testInvalidRowFromNonStringDateValue(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['available_at' => 123]));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertStringContainsString('строкой или датой', $outboxPendingRow->lastError->value());
    }

    public function testInvalidRowFromInvalidDateString(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['available_at' => 'not-date']));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertStringContainsString('некорректную дату', $outboxPendingRow->lastError->value());
    }

    public function testInvalidRowFromInvalidLastErrorType(): void
    {
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['last_error' => 123]));

        self::assertInstanceOf(InvalidOutboxPendingRow::class, $outboxPendingRow);
        self::assertStringContainsString('last_error', $outboxPendingRow->lastError->value());
    }

    public function testEmptyLastErrorIsTreatedAsNoError(): void
    {
        // Пустой last_error трактуется как «ошибки нет», поэтому строка остаётся валидной.
        $outboxPendingRow = OutboxPendingRow::fromDatabaseRow($this->validRow(['last_error' => '']));

        self::assertInstanceOf(ValidOutboxPendingRow::class, $outboxPendingRow);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validRow(array $overrides = []): array
    {
        return $overrides + [
            'id' => OutboxEventId::generate()->value(),
            'type' => OutboxDebugLogMessage::class,
            'payload' => '{"text":"debug","createdAt":"2026-05-25T16:06:00+00:00"}',
            'status' => OutboxEventStatus::Pending->value,
            'attempts' => 0,
            'available_at' => '2026-05-25T16:06:00+00:00',
            'queued_at' => null,
            'handled_at' => null,
            'failed_at' => null,
            'last_error' => null,
        ];
    }
}
