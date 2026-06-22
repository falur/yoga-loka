<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Message;

use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Outbox\Application\Message\OutboxMessage;

/**
 * Outbox-сообщение: загрузка подтверждена, нужна асинхронная обработка. Полезные данные — только
 * примитивы/enum (mediaId — строка, plan — MediaConversionPlan из публичных readonly-DTO с
 * точными list<...Spec>), чтобы ValinorOutboxMessageSerializer восстановил его без кастомных
 * конструкторов VO.
 */
final readonly class MediaUploaded implements OutboxMessage
{
    public function __construct(
        public string $mediaId,
        public MediaConversionPlan $plan,
    ) {}
}
