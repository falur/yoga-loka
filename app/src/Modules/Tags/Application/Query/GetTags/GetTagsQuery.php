<?php

declare(strict_types=1);

namespace App\Modules\Tags\Application\Query\GetTags;

/**
 * @param list<string> $tagIds
 */
final readonly class GetTagsQuery
{
    /**
     * @param list<string> $tagIds
     */
    public function __construct(
        public array $tagIds,
    ) {}
}
