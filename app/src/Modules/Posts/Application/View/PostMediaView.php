<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\View;

use App\Modules\Media\Public\Dto\MediaConversionDto;
use App\Modules\Media\Public\Dto\MediaOriginalDto;

/**
 * Вложение записи в read-model: позиция в упорядоченном наборе вложений принадлежит записи, поэтому
 * её держит Posts, а сами ссылки (оригинал и все готовые конверсии) приходят из публичных DTO Media —
 * клиент сам выбирает, что показать.
 *
 * Значение существует только за доступным медиа: недоступное медиа и медиа, у которого не осталось
 * ни оригинала, ни конверсий, в набор вложений не попадают (мягкая деградация на сборке).
 * original = null, если оригинал удалён, а конверсии остались.
 */
final readonly class PostMediaView
{
    /**
     * @param list<MediaConversionDto> $conversions
     */
    public function __construct(
        public string $id,
        public int $position,
        public MediaOriginalDto|null $original,
        public array $conversions,
    ) {}
}
