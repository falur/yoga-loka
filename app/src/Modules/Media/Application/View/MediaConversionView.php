<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\View;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;

/**
 * Одна конверсия медиа в read-model: вид (image/video/audio) и тип-профиль enum-ами (закрытый набор
 * значений контракта), ссылка и срок её действия (для presigned-ссылок private-медиа, иначе null).
 *
 * Enum-ы конверсий принадлежат Media — foundational-модулю (см. docs/arch.md): общая форма «медиа с
 * преобразованиями» нужна нескольким модулям, поэтому View живёт в Shared и ссылается на них напрямую.
 */
final readonly class MediaConversionView
{
    public function __construct(
        public MediaConversionKind $kind,
        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        public string $url,
        public \DateTimeImmutable|null $expiresAt,
    ) {}
}
