<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Data;

/**
 * Упорядоченный набор идентификаторов, принадлежащих одной записи (вложения или метки): объект,
 * а не вложенный массив — карта «запись -> список идентификаторов» держит это значение, а не
 * список напрямую, потому что тип контракта проекта не допускает вложенные массивы
 * (`array<string, list<string>>`).
 */
final readonly class PostRelatedIds
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        public array $ids,
    ) {}
}
