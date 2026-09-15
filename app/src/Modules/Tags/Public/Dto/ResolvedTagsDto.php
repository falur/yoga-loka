<?php

declare(strict_types=1);

namespace App\Modules\Tags\Public\Dto;

/**
 * Результат разрешения набора текстов в идентификаторы меток: идентификаторы в порядке первого
 * появления текста во входном наборе, повторы схлопнуты.
 */
final readonly class ResolvedTagsDto
{
    /**
     * @param list<string> $tagIds
     */
    public function __construct(
        public array $tagIds,
    ) {}
}
