<?php

declare(strict_types=1);

namespace App\Modules\Tags\Application\Query\GetTags;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\Repository\TagRepository;
use App\Modules\Tags\Domain\ValueObject\TagId;
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
    public function handle(GetTagsQuery $query): GetTagsResult
    {
        $tagIds = \array_map(
            static fn(string $tagId): TagId => TagId::fromString($tagId),
            $query->tagIds,
        );

        return new GetTagsResult(
            $this->tagRepository->findByIds(...$tagIds)
                ->toBase()
                ->keyBy(static fn(Tag $tag): string => $tag->id->value())
                ->map(static fn(Tag $tag): string => $tag->text->value()),
        );
    }
}
