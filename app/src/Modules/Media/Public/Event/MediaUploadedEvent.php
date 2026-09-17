<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Event;

use App\Modules\Media\Public\Dto\MediaConversionPlanDto;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;

/**
 * Интеграционное событие: загрузка подтверждена, нужна асинхронная обработка. Полезные данные —
 * только примитивы/enum (mediaId — строка, plan — MediaConversionPlanDto из публичных readonly-DTO
 * с точными list<...Dto>), чтобы ValinorOutboxMessageSerializer восстановил его без кастомных
 * конструкторов VO.
 */
final readonly class MediaUploadedEvent implements IntegrationEvent
{
    public function __construct(
        public string $mediaId,
        public MediaConversionPlanDto $plan,
    ) {}
}
