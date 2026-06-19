<?php

declare(strict_types=1);

namespace App\Modules\Tags\Application\Command\ResolveTags;

/**
 * @param list<string> $texts
 */
final readonly class ResolveTagsCommand
{
    /**
     * @param list<string> $texts
     */
    public function __construct(
        public array $texts,
        public string $creatorUserId,
    ) {}
}
