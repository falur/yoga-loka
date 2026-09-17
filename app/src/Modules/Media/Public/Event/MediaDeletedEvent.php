<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Event;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;

/**
 * Интеграционное событие: медиа фактически удалено (строка и все объекты в хранилище). Полезные
 * данные — только mediaId: сам объект уже не существует, поэтому дочитывать его содержимое
 * потребителю незачем — только снять свою ссылку на исчезнувший идентификатор.
 */
final readonly class MediaDeletedEvent implements IntegrationEvent
{
    public function __construct(
        public string $mediaId,
    ) {}
}
