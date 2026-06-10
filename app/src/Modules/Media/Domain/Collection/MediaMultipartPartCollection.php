<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, MediaMultipartPart>
 */
final class MediaMultipartPartCollection extends Collection
{
    /**
     * @param iterable<array-key, MediaMultipartPart> $parts
     */
    public function __construct(iterable $parts = [])
    {
        $sortedParts = $this->normalizeParts($parts);

        parent::__construct($sortedParts);
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return $this
            ->toBase()
            ->map(static fn(MediaMultipartPart $part): array => $part->jsonSerialize())
            ->values()
            ->all();
    }

    /**
     * @param iterable<array-key, MediaMultipartPart> $parts
     * @return list<MediaMultipartPart>
     */
    private function normalizeParts(iterable $parts): array
    {
        $numbers = [];
        $normalizedParts = [];

        foreach ($parts as $part) {
            $partNumber = $part->partNumber->value();

            if (\array_key_exists(key: $partNumber, array: $numbers)) {
                throw new InvalidDomainValueException('Номер части загрузки повторяется.');
            }

            $numbers[$partNumber] = true;
            $normalizedParts[] = $part;
        }

        \usort(
            array: $normalizedParts,
            callback: static fn(MediaMultipartPart $firstPart, MediaMultipartPart $secondPart): int => $firstPart->partNumber->value() <=> $secondPart->partNumber->value(),
        );

        return $normalizedParts;
    }
}
