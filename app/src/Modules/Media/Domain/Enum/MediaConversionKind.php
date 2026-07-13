<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

/**
 * Вид конверсии для рендера на клиенте: только image/video/audio. Документ не конвертируется, поэтому
 * варианта document здесь нет (в отличие от MediaType): потребитель с исчерпывающим match по kind не
 * тянет заведомо мёртвую ветку document. Постер видео имеет kind = image, т.к. это кадр-картинка.
 */
enum MediaConversionKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
}
