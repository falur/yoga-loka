<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaAttachable;

/**
 * Подтверждение, что весь набор медиа можно вложить: каждое существует, принадлежит владельцу и
 * готово (Ready). Порядок идентификаторов совпадает с порядком запроса.
 */
final readonly class CheckMediaAttachableResult
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public array $mediaIds,
    ) {}
}
