<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Backing-VO колонки action_id (nullable). Null-object: пустое значение = «перехода нет»
 * (none()), непустое = идентификатор цели deep-link. Отдельный typecast хранит none() как NULL.
 */
final readonly class NotificationActionId implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    public static function none(): self
    {
        return new self(value: '');
    }

    public static function of(string $value): self
    {
        $value = \trim($value);

        if ($value === '' || \mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Идентификатор цели перехода имеет неверную длину.');
        }

        return new self(value: $value);
    }

    public function value(): string|null
    {
        return $this->value === '' ? null : $this->value;
    }

    public function isPresent(): bool
    {
        return $this->value !== '';
    }

    public function presentValue(): string
    {
        if ($this->value === '') {
            throw new InvalidDomainValueException('Идентификатор цели перехода отсутствует.');
        }

        return $this->value;
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
    public function jsonSerialize(): string|null
    {
        return $this->value();
    }
}
