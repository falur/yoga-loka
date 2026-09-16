<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Tags\Domain\Collection\TagCollection;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\Repository\TagRepository;
use App\Modules\Tags\Domain\ValueObject\TagId;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Columns\TagColumns;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Entity\CycleTagEntity;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Mapper\TagMapper;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleTagEntity>
 */
final class CycleTagRepository extends AbstractRepository implements TagRepository
{
    /**
     * @param Select<CycleTagEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private TagMapper $tagMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(TagId $tagId): Tag|null
    {
        /** @var CycleTagEntity|null $cycleEntity */
        $cycleEntity = $this->findByPK($tagId->value());

        return $cycleEntity === null ? null : $this->tagMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByText(TagText $text): Tag|null
    {
        /** @var CycleTagEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([TagColumns::TEXT => $text->value()]);

        return $cycleEntity === null ? null : $this->tagMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByTexts(TagText ...$tagTexts): TagCollection
    {
        if ($tagTexts === []) {
            return new TagCollection();
        }

        $tagCollection = new TagCollection();

        /** @var iterable<CycleTagEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(TagColumns::TEXT, 'in', new Parameter(\array_map(
                static fn(TagText $tagText): string => $tagText->value(),
                $tagTexts,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $tagCollection->push($this->tagMapper->toDomain($cycleEntity));
        }

        return $tagCollection;
    }

    #[\Override]
    public function findByIds(TagId ...$tagIds): TagCollection
    {
        if ($tagIds === []) {
            return new TagCollection();
        }

        $tagCollection = new TagCollection();

        /** @var iterable<CycleTagEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(TagColumns::ID, 'in', new Parameter(\array_map(
                static fn(TagId $tagId): string => $tagId->value(),
                $tagIds,
            )))
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $tagCollection->push($this->tagMapper->toDomain($cycleEntity));
        }

        return $tagCollection;
    }

    #[\Override]
    public function saveAll(TagCollection $tags): void
    {
        if ($tags->isEmpty()) {
            return;
        }

        foreach ($tags as $tag) {
            $this->entityManager->persist($this->tagMapper->toCycleEntity($tag));
        }

        $this->entityManager->run();
    }
}
