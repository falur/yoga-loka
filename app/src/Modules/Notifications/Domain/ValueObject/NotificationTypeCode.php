<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Код вида уведомления в формате `module.action` (например, `chat.message_received`).
 * Виды регистрируют модули-источники; ядро уведомлений код не интерпретирует, только хранит,
 * валидирует формат и ищет по нему определение в реестре.
 */
final readonly class NotificationTypeCode implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 255;
    private const string FORMAT = '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/';

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = \trim($value);

        if (\mb_strlen($value) > self::MAX_LENGTH || \preg_match(pattern: self::FORMAT, subject: $value) !== 1) {
            throw new InvalidDomainValueException('Код вида уведомления должен быть в формате module.action.');
        }

        return new self(value: $value);
    }

    public function value(): string
    {
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
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
