<?php

declare(strict_types=1);

namespace App\Modules\Tags\Public\Dto;

/**
 * Публичная метка для соседей: идентификатор и текст. Внутренняя карта «идентификатор -> текст»
 * остаётся внутренней формой Tags, соседу метка приходит одним значением.
 */
final readonly class TagDto
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}
}
