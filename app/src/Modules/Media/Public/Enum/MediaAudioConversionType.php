<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Enum;

/**
 * Публичный дубликат доменного MediaAudioConversionType: домен не вправе зависеть от Public,
 * поэтому набор вариантов и их строковые значения повторяются здесь и держатся в синхронном
 * состоянии unit-проверкой совпадения. Преобразование в доменный вариант — по строковому значению.
 */
enum MediaAudioConversionType: string
{
    case NormalizedAacM4a = 'normalizedAacM4a';
}
