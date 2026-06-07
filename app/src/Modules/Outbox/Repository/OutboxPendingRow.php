<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Repository;

use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Domain\ValueObject\OutboxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxAvailableAt;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventDate;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;

/**
 * Типизированное представление сырой pending-строки outbox до ORM-гидрации.
 *
 * Строка читается на границе системы, где значения имеют неизвестный тип. Парсинг
 * сужает каждое поле в типизированный VO/enum. Если данные повреждены, строка
 * представляется как InvalidOutboxPendingRow с идентификатором и ошибкой, чтобы
 * orchestrator (а не репозиторий) принял решение «строка битая → failed».
 */
abstract readonly class OutboxPendingRow
{
    /**
     * @param array<int|string, mixed> $fields
     */
    public static function fromDatabaseRow(array $fields): self
    {
        $rawId = $fields['id'] ?? null;

        if (!\is_string($rawId)) {
            // Нестроковый id — повреждённая строка. Возвращаем InvalidOutboxPendingRow с
            // приведённым к строке id, чтобы recovery пометил строку failed, а не молча
            // пропустил её и не зациклил relay на той же ошибке гидрации.
            return new InvalidOutboxPendingRow(
                rawOutboxEventId: self::rawIdToString($rawId),
                lastError: OutboxLastError::fromString('Поле `id` должно быть строкой.'),
            );
        }

        try {
            // Полный разбор каждого поля — зонд порчи: любое битое поле бросает исключение
            // и переводит строку в InvalidOutboxPendingRow. Разобранные значения не
            // удерживаются: recovery валидную строку пропускает, поэтому ему достаточно
            // факта «строка читается без ошибок».
            self::assertRowParses($fields);

            return new ValidOutboxPendingRow();
        } catch (\Throwable $exception) {
            return new InvalidOutboxPendingRow(
                rawOutboxEventId: $rawId,
                lastError: OutboxLastError::fromThrowable($exception),
            );
        }
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private static function assertRowParses(array $fields): void
    {
        OutboxEventId::fromString(self::stringField(fields: $fields, field: 'id'));
        OutboxEventType::fromString(self::stringField(fields: $fields, field: 'type'));
        OutboxEventPayload::fromJson(self::stringField(fields: $fields, field: 'payload'));
        OutboxEventStatus::from(self::stringField(fields: $fields, field: 'status'));
        OutboxAttempts::fromInt(self::attemptsField($fields));
        OutboxAvailableAt::fromDateTime(self::dateField(fields: $fields, field: 'available_at'));
        self::nullableDateField(fields: $fields, field: 'queued_at');
        self::nullableDateField(fields: $fields, field: 'handled_at');
        self::nullableDateField(fields: $fields, field: 'failed_at');
        self::lastErrorField($fields);
    }

    private static function rawIdToString(mixed $rawId): string
    {
        if (\is_scalar($rawId)) {
            return (string) $rawId;
        }

        return \get_debug_type($rawId);
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private static function stringField(array $fields, string $field): string
    {
        $value = $fields[$field] ?? null;

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Поле `%s` должно быть строкой.', $field));
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private static function attemptsField(array $fields): int
    {
        $value = $fields['attempts'] ?? null;

        if (\is_string($value) && \preg_match(pattern: '/^-?\d+$/', subject: $value) === 1) {
            return (int) $value;
        }

        if (!\is_int($value)) {
            throw new \UnexpectedValueException('Поле `attempts` должно быть целым числом.');
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private static function dateField(array $fields, string $field): \DateTimeImmutable
    {
        $value = $fields[$field] ?? null;

        if ($value === null) {
            throw new \UnexpectedValueException(\sprintf('Поле `%s` не должно быть NULL.', $field));
        }

        return self::toDateTime(value: $value, field: $field);
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private static function nullableDateField(array $fields, string $field): OutboxEventDate
    {
        $value = $fields[$field] ?? null;

        if ($value === null) {
            return OutboxEventDate::none();
        }

        return OutboxEventDate::fromDateTime(self::toDateTime(value: $value, field: $field));
    }

    private static function toDateTime(mixed $value, string $field): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Поле `%s` должно быть строкой или датой.', $field));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new \UnexpectedValueException(\sprintf('Поле `%s` содержит некорректную дату.', $field));
        }
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private static function lastErrorField(array $fields): OutboxLastError
    {
        $value = $fields['last_error'] ?? null;

        if ($value === null) {
            return OutboxLastError::none();
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException('Поле `last_error` должно быть строкой или NULL.');
        }

        // Пустая строка трактуется как «ошибки нет» — так же, как NULL и как typecast-слой.
        if (\trim($value) === '') {
            return OutboxLastError::none();
        }

        return OutboxLastError::fromString($value);
    }
}
