<?php

declare(strict_types=1);

namespace App\Modules\Tags\Application\Query\GetTags;

use App\Modules\Tags\Application\Dto\TagTextCollection;
use App\Modules\Tags\Repository\TagRepository;
use App\Shared\Domain\ValueObject\TagId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Возвращает карту «идентификатор тега -> текст» по списку идентификаторов. Несуществующие
 * идентификаторы просто отсутствуют в результате (их не было среди найденных тегов).
 */
final readonly class GetTagsHandler
{
    public function __construct(
        private TagRepository $tagRepository,
    ) {}

    #[LogOperation]
    public function handle(GetTagsQuery $query): TagTextCollection
    {
        $tagIds = \array_map(
            static fn(string $tagId): TagId => TagId::fromString($tagId),
            $query->tagIds,
        );

        $map = [];

        foreach ($this->tagRepository->findByIds(...$tagIds) as $tag) {
            $map[$tag->id->value()] = $tag->text->value();
        }

        return new TagTextCollection($map);
    }
}
