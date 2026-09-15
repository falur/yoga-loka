<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Modules\Media\Public\Dto\MediaConversionDto;

/**
 * Одна конверсия аватара в realtime-payload (Centrifugo): вид и тип-профиль (строковые значения enum),
 * ссылка и срок её действия (ATOM-строка для presigned-ссылок private-медиа, иначе null). Форма
 * совпадает с MediaConversionResource HTTP-ответа.
 */
final readonly class RealtimeMediaConversionPayload implements \JsonSerializable
{
    public function __construct(
        private string $kind,
        private string $type,
        private string $url,
        private \DateTimeImmutable|null $expiresAt,
    ) {}

    public static function fromDto(MediaConversionDto $conversion): self
    {
        return new self(
            kind: $conversion->kind->value,
            type: $conversion->type->value,
            url: $conversion->url,
            expiresAt: $conversion->expiresAt,
        );
    }

    /**
     * @return array<string, string|null>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'kind' => $this->kind,
            'type' => $this->type,
            'url' => $this->url,
            'expiresAt' => $this->expiresAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
