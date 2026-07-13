<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

/**
 * Автор-инициатор в realtime-payload (Centrifugo): id, имя и аватар одним MediaView (RealtimeMediaPayload)
 * либо null, если аватара нет или его медиа недоступно. В отличие от NotificationActorPayload
 * пайплайна (несёт только id медиа), здесь аватар уже разрешён в готовый набор ссылок — открытое
 * приложение показывает аватар той же формой, что и список инбокса, без запроса к профилю.
 */
final readonly class RealtimeActorPayload
{
    public function __construct(
        public string $id,
        public string $name,
        public RealtimeMediaPayload|null $avatar,
    ) {}
}
