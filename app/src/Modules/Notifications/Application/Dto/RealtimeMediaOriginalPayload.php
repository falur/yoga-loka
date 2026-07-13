<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Shared\Application\View\MediaOriginalView;

/**
 * Оригинал аватара в realtime-payload (Centrifugo): ссылка и срок её действия (ATOM-строка для
 * presigned-ссылки private-медиа, иначе null). Форма совпадает с MediaOriginalResource HTTP-ответа,
 * чтобы клиент показывал одно и то же MediaView в списке инбокса и в живом уведомлении.
 */
final readonly class RealtimeMediaOriginalPayload implements \JsonSerializable
{
    public function __construct(
        private string $url,
        private \DateTimeImmutable|null $expiresAt,
    ) {}

    public static function fromView(MediaOriginalView $original): self
    {
        return new self(url: $original->url, expiresAt: $original->expiresAt);
    }

    /**
     * @return array<string, string|null>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'url' => $this->url,
            'expiresAt' => $this->expiresAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
