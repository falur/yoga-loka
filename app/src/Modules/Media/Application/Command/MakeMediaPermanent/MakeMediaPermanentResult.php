<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\MakeMediaPermanent;

/**
 * Идентификаторы медиа, переведённых в постоянное состояние, в порядке передачи. Снимок состояния
 * одного медиа (MediaResult) для пакетного сценария не подходит: сценарий меняет набор.
 */
final readonly class MakeMediaPermanentResult
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(
        public array $mediaIds,
    ) {}
}
