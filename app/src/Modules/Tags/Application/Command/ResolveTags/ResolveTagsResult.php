<?php

declare(strict_types=1);

namespace App\Modules\Tags\Application\Command\ResolveTags;

/**
 * @param list<string> $tagIds
 */
final readonly class ResolveTagsResult
{
    /**
     * @param list<string> $tagIds
     */
    public function __construct(
        public array $tagIds,
    ) {}
}
