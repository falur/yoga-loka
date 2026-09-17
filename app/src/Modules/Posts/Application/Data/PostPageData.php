<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Data;

/**
 * Страница данных чтения ленты: набор записей и курсор следующей страницы. Курсор и нарезку
 * страницы считает `CursorSlice::fromOverfetched()` в Reader, а не этот класс.
 */
final readonly class PostPageData
{
    public function __construct(
        public PostDataCollection $posts,
        public string|null $nextCursor,
    ) {}
}
