<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Result;

use App\Modules\Media\Public\Dto\MediaConversionDto;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Media\Public\Dto\MediaOriginalDto;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Entity\PostMedia;

/**
 * Вложение записи в ответе: позиция в упорядоченном наборе вложений принадлежит записи, поэтому
 * её держит Posts, а сами ссылки (оригинал и все готовые конверсии) приходят из публичных DTO Media —
 * клиент сам выбирает, что показать.
 *
 * Значение существует только за доступным медиа: недоступное медиа и медиа, у которого не осталось
 * ни оригинала, ни конверсий, в набор вложений не попадают (мягкая деградация на сборке).
 * original = null, если оригинал удалён, а конверсии остались.
 */
final readonly class PostMediaResult
{
    /**
     * @param list<MediaConversionDto> $conversions
     */
    public function __construct(
        public string $id,
        public int $position,
        public MediaOriginalDto|null $original,
        public array $conversions,
    ) {}

    /**
     * Вложения записи из общего батча (Entity-путь: GetPost/GetPostComments) — позиция берётся из
     * PostMedia (внутренняя сущность агрегата).
     *
     * @return list<self>
     */
    public static function listFromPostMedia(PostMediaCollection $postMedia, MediaDtoCollection $urls): array
    {
        $items = [];

        foreach ($postMedia as $item) {
            $result = self::fromMediaId(mediaId: $item->mediaId->value(), position: $item->position->value(), urls: $urls);

            if ($result !== null) {
                $items[] = $result;
            }
        }

        return $items;
    }

    /**
     * Вложения записи из общего батча (Data-путь: GetMyFeed/GetUserFeed) — идентификаторы уже
     * упорядочены Reader-ом по возрастанию позиции: позиции вложений записи всегда плотная
     * последовательность 0..N-1 (назначаются подряд при создании и не меняются), поэтому индекс
     * элемента в списке равен хранимой позиции.
     *
     * @param list<string> $mediaIdsInOrder
     *
     * @return list<self>
     */
    public static function listFromOrderedIds(array $mediaIdsInOrder, MediaDtoCollection $urls): array
    {
        $items = [];

        foreach ($mediaIdsInOrder as $position => $mediaId) {
            $result = self::fromMediaId(mediaId: $mediaId, position: $position, urls: $urls);

            if ($result !== null) {
                $items[] = $result;
            }
        }

        return $items;
    }

    /**
     * Берёт ссылки вложения из общего батча Media по идентификатору медиа.
     *
     * Показывать нечего: медиа недоступно (в батче его нет — не найдено или не финализировано) или
     * у него нет ни оригинала, ни конверсий -> вложение исключается (мягкая деградация, без 500).
     * Оригинал мог быть удалён (readyOriginalRemoved) — тогда original = null, но по оставшимся
     * конверсиям вложение показывается.
     */
    private static function fromMediaId(string $mediaId, int $position, MediaDtoCollection $urls): self|null
    {
        $media = $urls->get($mediaId);

        if ($media === null || ($media->original === null && $media->conversions === [])) {
            return null;
        }

        return new self(
            id: $media->id,
            position: $position,
            original: $media->original,
            conversions: $media->conversions,
        );
    }
}
