<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Result;

use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Tags\Public\Dto\TagDto;
use App\Modules\Tags\Public\Dto\TagDtoCollection;

/**
 * Метка записи в ответе: идентификатор и текст.
 */
final readonly class TagResult
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}

    public static function fromDto(TagDto $tag): self
    {
        return new self(id: $tag->id, text: $tag->text);
    }

    /**
     * Строит метки записи из общего батча по связям записи (Entity-путь: GetPost/GetPostComments).
     * Метки нет в батче — её не существует у соседа, поэтому связь пропускается (мягкая деградация,
     * без 500). Порядок меток в ответе частью контракта не является (в post_tags нет колонки
     * позиции — порядок добавления не хранится).
     *
     * @return list<self>
     */
    public static function listFromPostTags(PostTagCollection $postTags, TagDtoCollection $tags): array
    {
        $results = [];

        foreach ($postTags as $postTag) {
            $tag = $tags->get($postTag->tagId->value());

            if ($tag === null) {
                continue;
            }

            $results[] = self::fromDto($tag);
        }

        return $results;
    }

    /**
     * Строит метки записи из общего батча по набору идентификаторов (Data-путь: GetMyFeed/GetUserFeed).
     *
     * @param list<string> $tagIds
     *
     * @return list<self>
     */
    public static function listFromIds(array $tagIds, TagDtoCollection $tags): array
    {
        $results = [];

        foreach ($tagIds as $tagId) {
            $tag = $tags->get($tagId);

            if ($tag === null) {
                continue;
            }

            $results[] = self::fromDto($tag);
        }

        return $results;
    }
}
