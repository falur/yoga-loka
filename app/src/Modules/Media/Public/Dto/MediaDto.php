<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Dto;

/**
 * Медиа с преобразованиями в межмодульном ответе: идентификатор, оригинал (ссылка и срок) и все его
 * готовые конверсии — потребитель сам выбирает, что показать.
 *
 * Позиции здесь нет: позиция вложения принадлежит записи, а не медиа, поэтому её держит собственное
 * представление вложения у потребителя.
 *
 * original = null, если оригинал удалён (readyOriginalRemoved) — конверсии при этом продолжают
 * резолвиться. Недоступного медиа в ответе просто нет, поэтому отсутствие идентификатора в
 * MediaDtoCollection и означает «медиа недоступно».
 */
final readonly class MediaDto
{
    /**
     * @param list<MediaConversionDto> $conversions
     */
    public function __construct(
        public string $id,
        public MediaOriginalDto|null $original,
        public array $conversions,
    ) {}
}
