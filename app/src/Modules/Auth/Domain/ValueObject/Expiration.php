<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Trait\ComparesDateTimeToMicroseconds;

/**
 * Момент истечения срока (кода, талона, токена). Единый VO для всех TTL: отличается только
 * длительностью при создании. after() считает now + seconds, fromDateTime восстанавливает
 * сохранённый момент (граница БД через ExpirationTypecast).
 */
final readonly class Expiration implements \Stringable, \JsonSerializable
{
    use ComparesDateTimeToMicroseconds;

    private function __construct(
        private \DateTimeImmutable $value,
    ) {}

    public static function after(\DateTimeImmutable $now, int $seconds): self
    {
        return new self(value: $now->add(new \DateInterval(\sprintf('PT%dS', $seconds))));
    }

    public static function fromDateTime(\DateTimeImmutable $value): self
    {
        return new self(value: $value);
    }

    public function value(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now >= $this->value;
    }

    public function equals(self $other): bool
    {
        return self::dateTimeEqualsToMicroseconds(value: $this->value, other: $other->value);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }
}
