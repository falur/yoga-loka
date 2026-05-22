<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class MediaExpiration implements \Stringable, \JsonSerializable
{
    private function __construct(
        private ?\DateTimeImmutable $expiresAt,
    ) {}

    public static function permanent(): self
    {
        return new self(expiresAt: null);
    }

    public static function temporaryUntil(\DateTimeImmutable $expiresAt): self
    {
        return new self(expiresAt: $expiresAt);
    }

    public function value(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isPermanent(): bool
    {
        return $this->expiresAt === null;
    }

    public function isTemporary(): bool
    {
        return $this->expiresAt !== null;
    }

    public function expiresAtOrFail(): \DateTimeImmutable
    {
        if ($this->expiresAt === null) {
            throw new InvalidDomainValueException('Постоянный файл не имеет даты удаления.');
        }

        return $this->expiresAt;
    }

    public function equals(self $other): bool
    {
        return $this->value()?->getTimestamp() === $other->value()?->getTimestamp();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->expiresAt?->format(\DateTimeInterface::ATOM) ?? '';
    }

    #[\Override]
    public function jsonSerialize(): ?string
    {
        return $this->expiresAt?->format(\DateTimeInterface::ATOM);
    }
}
