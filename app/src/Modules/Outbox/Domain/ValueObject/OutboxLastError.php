<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class OutboxLastError implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 2000;

    private function __construct(
        private string $value,
    ) {}

    public static function none(): self
    {
        return new self(value: '');
    }

    public static function fromString(string $value): self
    {
        $value = \trim($value);

        if ($value === '' || \mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Ошибка outbox имеет неверную длину.');
        }

        return new self(value: $value);
    }

    public static function fromThrowable(\Throwable $exception): self
    {
        return self::fromString(
            \mb_substr(
                string: \sprintf('%s: %s', $exception::class, $exception->getMessage()),
                start: 0,
                length: self::MAX_LENGTH,
            ),
        );
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * Единый контракт хранения ошибки в строковой колонке БД: пустое значение
     * пишется как NULL. Используется и typecast-слоем, и ручным UPDATE по сырому id,
     * чтобы трактовка пустого значения не разъезжалась между этими местами.
     */
    public function toDatabaseValue(): string|null
    {
        return $this->isEmpty() ? null : $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
