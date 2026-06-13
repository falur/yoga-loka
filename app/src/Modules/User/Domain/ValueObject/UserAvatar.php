<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

final readonly class UserAvatar implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string|null $mediaId,
    ) {}

    public static function none(): self
    {
        return new self(mediaId: null);
    }

    public static function pointingTo(string $mediaId): self
    {
        if (!AbstractUuidV7Id::isUuidV7($mediaId)) {
            throw new InvalidDomainValueException('Аватар должен ссылаться на UUID v7 медиа.');
        }

        return new self(mediaId: \strtolower($mediaId));
    }

    public function value(): string|null
    {
        return $this->mediaId;
    }

    public function isEmpty(): bool
    {
        return $this->mediaId === null;
    }

    public function equals(self $avatar): bool
    {
        return $this->mediaId === $avatar->mediaId;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->mediaId ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->mediaId;
    }
}
