<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

/**
 * @template T of object
 */
final class PaginationResponse extends AbstractJsonResponse
{
    /**
     * @param list<T> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly PaginationMetaResponse $meta,
    ) {}
}
