<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaAttachable;

/**
 * Пакетная проверка пригодности набора медиа к вложению: потребитель передаёт все идентификаторы
 * одной записи разом, а не вызывает сценарий на каждое медиа.
 */
final readonly class CheckMediaAttachableQuery
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public array $mediaIds,
        public string $ownerUserId,
    ) {}
}
