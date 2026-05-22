<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class MediaMultipartUploadIdValue implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 1024;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = \trim($value);

        if ($value === '' || \strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Идентификатор multipart upload имеет неверную длину.');
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
