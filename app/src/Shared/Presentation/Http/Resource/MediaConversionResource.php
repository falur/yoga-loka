<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Resource;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Shared\Application\View\MediaConversionView;

/**
 * Одна конверсия медиа в ответе API: вид (image/video/audio) и тип-профиль — enum-ами (в JSON
 * сериализуются в свои строковые значения, а в OpenAPI дают закрытый набор), ссылка и срок действия
 * (для presigned-ссылок private-медиа, иначе null). Одна форма для вложений записи и аватара автора —
 * фронт сам решает, какой профиль показать.
 */
final readonly class MediaConversionResource extends AbstractResource
{
    public function __construct(
        public MediaConversionKind $kind,
        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}

    public static function fromView(MediaConversionView $conversion): self
    {
        return new self(
            kind: $conversion->kind,
            type: $conversion->type,
            url: $conversion->url,
            expiresAt: $conversion->expiresAt,
        );
    }
}
