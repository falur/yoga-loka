<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Spiral\PublicApi;

use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsCommand;
use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsHandler;
use App\Modules\Tags\Application\Query\GetTags\GetTagsHandler;
use App\Modules\Tags\Application\Query\GetTags\GetTagsQuery;
use App\Modules\Tags\Public\Contract\TagsContract;
use App\Modules\Tags\Public\Dto\ResolvedTagsDto;
use App\Modules\Tags\Public\Dto\TagDto;
use App\Modules\Tags\Public\Dto\TagDtoCollection;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Входной адаптер публичного контракта Tags: раскладывает вызовы соседей в существующие сценарии
 * модуля и переводит их внутренние формы ответа в публичные DTO. Правил здесь нет — дедупликация
 * текстов, создание недостающих меток и отсутствие несуществующих в выборке остаются в сценариях.
 *
 * Разрешение диспатчится командной шиной, поэтому внутри транзакции соседа остаётся вложенным
 * (#[Transactional] -> SAVEPOINT) — так же, как до появления контракта.
 */
final readonly class TagsProvider implements TagsContract
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private ResolveTagsHandler $resolveTagsHandler,
        private GetTagsHandler $getTagsHandler,
    ) {}

    /**
     * @param list<string> $texts
     */
    #[\Override]
    public function resolve(array $texts, string $creatorUserId): ResolvedTagsDto
    {
        $result = $this->commandBus->dispatch(
            command: new ResolveTagsCommand(texts: $texts, creatorUserId: $creatorUserId),
            handler: $this->resolveTagsHandler->handle(...),
        );

        return new ResolvedTagsDto(tagIds: $result->tagIds);
    }

    /**
     * @param list<string> $tagIds
     */
    #[\Override]
    public function textsByIds(array $tagIds): TagDtoCollection
    {
        $texts = $this->queryBus->dispatch(
            query: new GetTagsQuery(tagIds: $tagIds),
            handler: $this->getTagsHandler->handle(...),
        );

        return new TagDtoCollection(
            $texts->toBase()->map(static fn(string $text, string $tagId): TagDto => new TagDto(
                id: $tagId,
                text: $text,
            )),
        );
    }
}
