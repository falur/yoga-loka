<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

/**
 * Тег записи в read-model: идентификатор и текст.
 */
final readonly class TagView
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}
}
