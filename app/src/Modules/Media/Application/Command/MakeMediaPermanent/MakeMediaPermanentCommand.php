<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\MakeMediaPermanent;

/**
 * Пакетный перевод набора медиа в постоянное состояние: потребитель передаёт все идентификаторы
 * одной записи разом, а не вызывает сценарий на каждое медиа.
 */
final readonly class MakeMediaPermanentCommand
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public string $userId,
        public array $mediaIds,
    ) {}
}
