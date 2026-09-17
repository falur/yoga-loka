<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Enum;

/**
 * Публичный дубликат доменного MediaConversionKind: домен не вправе зависеть от Public, поэтому
 * набор вариантов и их строковые значения повторяются здесь и держатся в синхронном состоянии
 * unit-проверкой совпадения. Преобразование доменного варианта в публичный — по строковому значению.
 *
 * Вид конверсии для рендера на клиенте: только image/video/audio. Постер видео имеет вид image,
 * потому что это кадр-картинка.
 */
enum MediaConversionKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
}
