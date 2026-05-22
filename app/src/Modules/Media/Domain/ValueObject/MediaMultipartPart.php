<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

final readonly class MediaMultipartPart implements \JsonSerializable
{
    private function __construct(
        public MediaMultipartPartNumber $partNumber,
        public MediaMultipartPartETag $eTag,
    ) {}

    public static function create(MediaMultipartPartNumber $partNumber, MediaMultipartPartETag $eTag): self
    {
        return new self(partNumber: $partNumber, eTag: $eTag);
    }

    public static function fromValues(int $partNumber, string $eTag): self
    {
        return new self(
            partNumber: MediaMultipartPartNumber::fromInt($partNumber),
            eTag: MediaMultipartPartETag::fromString($eTag),
        );
    }

    public function equals(self $other): bool
    {
        return $this->partNumber->equals($other->partNumber)
            && $this->eTag->equals($other->eTag);
    }

    /**
     * @return array<string, int|string>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'partNumber' => $this->partNumber->value(),
            'eTag' => $this->eTag->value(),
        ];
    }
}
