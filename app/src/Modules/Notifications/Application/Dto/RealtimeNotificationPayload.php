<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

/**
 * Типизированный payload realtime-сообщения (Centrifugo). Сериализуется в camelCase JSON
 * (rules.md:65). action — вложенный nullable DTO либо null, actor — снимок автора с аватаром-MediaView
 * (RealtimeActorPayload) либо null (открытое приложение покажет аватар без запроса к профилю).
 */
final readonly class RealtimeNotificationPayload implements \JsonSerializable
{
    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public NotificationActionPayload|null $action,
        public RealtimeActorPayload|null $actor,
        public string $createdAt,
    ) {}

    /**
     * @return array<string, string|NotificationActionPayload|RealtimeActorPayload|null>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'action' => $this->action,
            'actor' => $this->actor,
            'createdAt' => $this->createdAt,
        ];
    }
}
