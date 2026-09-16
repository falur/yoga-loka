<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Tags\Domain\Collection\TagCollection;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\Repository\TagRepository;
use App\Modules\Tags\Domain\ValueObject\TagId;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<Tag>
 */
final class CycleTagRepository extends AbstractRepository implements TagRepository
{
    /**
     * @param Select<Tag> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findById(TagId $tagId): Tag|null
    {
        return $this->findByPK($tagId->value());
    }

    #[\Override]
    public function findByText(TagText $text): Tag|null
    {
        return $this->findOne(['text' => $text->value()]);
    }

    #[\Override]
    public function findByTexts(TagText ...$tagTexts): TagCollection
    {
        if ($tagTexts === []) {
            return new TagCollection();
        }

        return new TagCollection(
            $this->select()
                ->where('text', 'in', new Parameter(\array_map(
                    static fn(TagText $tagText): string => $tagText->value(),
                    $tagTexts,
                )))
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findByIds(TagId ...$tagIds): TagCollection
    {
        if ($tagIds === []) {
            return new TagCollection();
        }

        return new TagCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(TagId $tagId): string => $tagId->value(),
                    $tagIds,
                )))
                ->fetchAll(),
        );
    }

    #[\Override]
    public function saveAll(TagCollection $tags): void
    {
        if ($tags->isEmpty()) {
            return;
        }

        foreach ($tags as $tag) {
            $this->entityManager->persist($tag);
        }

        $this->entityManager->run();
    }
}
