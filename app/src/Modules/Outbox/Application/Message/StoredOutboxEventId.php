<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Message;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class StoredOutboxEventId
{
    private function __construct(
        private string $value,
    ) {
        if ($value === '') {
            throw new InvalidDomainValueException('Идентификатор outbox-события не может быть пустым.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
