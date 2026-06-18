<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

/**
 * Ссылка репоста на исходную запись (parent_post_id): null-object VO поверх UUID v7.
 * none() — запись не репост; не выражается relation, потому что relation не моделирует «нет родителя».
 */
final readonly class PostOriginal implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string|null $postId,
    ) {}

    public static function none(): self
    {
        return new self(postId: null);
    }

    public static function pointingTo(string $postId): self
    {
        if (!AbstractUuidV7Id::isUuidV7($postId)) {
            throw new InvalidDomainValueException('Ссылка на исходную запись должна быть UUID v7.');
        }

        return new self(postId: \strtolower($postId));
    }

    public function value(): string|null
    {
        return $this->postId;
    }

    public function isEmpty(): bool
    {
        return $this->postId === null;
    }

    public function equals(self $original): bool
    {
        return $this->postId === $original->postId;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->postId ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->postId;
    }
}
