<?php

declare(strict_types=1);

namespace Tests\Support\Outbox;

use GianTiaga\SpiralOutbox\Database\OutboxDeliveryColumns;
use GianTiaga\SpiralOutbox\OutboxDeliveryStatus;

/**
 * Строка доставки обмена в форме, удобной проверке: идентификаторы, класс Job, очередь, статус и
 * число попыток. Ряд выборки читается в одном месте — фабрике этого объекта, а имена колонок
 * берутся из каталога колонок пакета.
 */
final readonly class OutboxDeliveryRow
{
    public function __construct(
        public string $outboxDeliveryId,
        public string $outboxEventId,
        public string $jobClass,
        public string $queueName,
        public OutboxDeliveryStatus $status,
        public int $attempts,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            outboxDeliveryId: self::scalarValue(row: $row, column: OutboxDeliveryColumns::ID),
            outboxEventId: self::scalarValue(row: $row, column: OutboxDeliveryColumns::EVENT_ID),
            jobClass: self::scalarValue(row: $row, column: OutboxDeliveryColumns::JOB_CLASS),
            queueName: self::scalarValue(row: $row, column: OutboxDeliveryColumns::QUEUE_NAME),
            status: OutboxDeliveryStatus::from(
                self::scalarValue(row: $row, column: OutboxDeliveryColumns::STATUS),
            ),
            attempts: (int) self::scalarValue(row: $row, column: OutboxDeliveryColumns::ATTEMPTS),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function scalarValue(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (\is_string($value) || \is_int($value)) {
            return (string) $value;
        }

        throw new \RuntimeException(\sprintf('Колонка %s строки доставки outbox пуста.', $column));
    }
}
