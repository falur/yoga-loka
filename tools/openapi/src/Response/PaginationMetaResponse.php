<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

final readonly class PaginationMetaResponse implements \JsonSerializable
{
    public function __construct(
        public string|null $nextCursor,
        public int $limit,
    ) {}

    public function jsonSerialize(): mixed
    {
        return \get_object_vars($this);
    }
}
