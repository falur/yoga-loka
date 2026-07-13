<?php

declare(strict_types=1);

namespace App\Shared\Application\View;

/**
 * Общий read-model «медиа с преобразованиями»: оригинал (ссылка + срок) и все его конверсии одним
 * значением — клиент сам выбирает, что показать. Одна форма для вложений записи и аватара автора.
 * Значение существует только за реальной сущностью медиа, поэтому id всегда задан; отсутствие медиа
 * выражается как MediaView|null у потребителя (например, у аватара — null, заглушку ставит клиент).
 *
 * Неприменимое в контексте поле — null: position = null вне упорядоченного набора вложений,
 * original = null, если оригинал удалён (конверсии остаются).
 */
final readonly class MediaView
{
    /**
     * @param list<MediaConversionView> $conversions
     */
    public function __construct(
        public string $id,
        public int|null $position,
        public MediaOriginalView|null $original,
        public array $conversions,
    ) {}
}
