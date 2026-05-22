<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use Ramsey\Uuid\Uuid;

final readonly class MediaPath implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 1024;

    private function __construct(
        private string $value,
    ) {}

    public static function originalUpload(MediaStorageKey $storageKey, string $extension): self
    {
        $safeExtension = \strtolower(\trim($extension));

        if ($safeExtension === '' || \preg_match(pattern: '/^[a-z0-9]+$/', subject: $safeExtension) !== 1) {
            throw new InvalidDomainValueException('Расширение файла имеет неверный формат.');
        }

        return self::fromString(
            \sprintf('uploads/%s/%s/source.%s', $storageKey->shard(), $storageKey, $safeExtension),
        );
    }

    public static function fromString(string $value): self
    {
        $value = \trim($value);
        self::assertValid($value);

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

    private static function assertValid(string $value): void
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Путь файла слишком длинный.');
        }

        $matches = [];
        if (\preg_match(
            pattern: '/^(uploads|images|videos)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
            subject: $value,
            matches: $matches,
        ) !== 1) {
            throw new InvalidDomainValueException('Путь файла имеет неверный формат.');
        }

        if (!Uuid::isValid($matches[3]) || $matches[2] !== \substr(string: $matches[3], offset: 0, length: 2)) {
            throw new InvalidDomainValueException('Путь файла имеет неверный раздел.');
        }
    }
}
