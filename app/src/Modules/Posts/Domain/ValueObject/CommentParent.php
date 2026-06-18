<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

/**
 * Ссылка комментария на родительский комментарий (parent_comment_id): null-object VO поверх UUID v7.
 * none() — комментарий первого уровня; не выражается self-relation, потому что relation не моделирует «нет родителя».
 */
final readonly class CommentParent implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string|null $commentId,
    ) {}

    public static function none(): self
    {
        return new self(commentId: null);
    }

    public static function pointingTo(string $commentId): self
    {
        if (!AbstractUuidV7Id::isUuidV7($commentId)) {
            throw new InvalidDomainValueException('Ссылка на родительский комментарий должна быть UUID v7.');
        }

        return new self(commentId: \strtolower($commentId));
    }

    public function value(): string|null
    {
        return $this->commentId;
    }

    public function isEmpty(): bool
    {
        return $this->commentId === null;
    }

    public function equals(self $parent): bool
    {
        return $this->commentId === $parent->commentId;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->commentId ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->commentId;
    }
}
