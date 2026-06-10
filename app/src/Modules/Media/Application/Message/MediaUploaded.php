<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Message;

use App\Modules\Media\Application\Dto\MediaConversionSpec;
use App\Modules\Outbox\Application\Message\OutboxMessage;

/**
 * Outbox-сообщение: загрузка подтверждена, нужна асинхронная обработка. Payload — только
 * примитивы/enum (mediaId — строка, conversions — список публичных readonly-DTO
 * MediaConversionSpec), чтобы ValinorOutboxMessageSerializer восстановил его без кастомных
 * конструкторов VO.
 */
final readonly class MediaUploaded implements OutboxMessage
{
    /**
     * @param list<MediaConversionSpec> $conversions
     */
    public function __construct(
        public string $mediaId,
        public array $conversions,
    ) {}
}
