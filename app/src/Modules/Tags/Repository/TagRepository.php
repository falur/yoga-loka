<?php

declare(strict_types=1);

namespace App\Modules\Tags\Repository;

use App\Modules\Tags\Domain\Collection\TagCollection;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Domain\ValueObject\TagId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\Database\Injection\Parameter;

/**
 * @extends AbstractRepository<Tag>
 */
final class TagRepository extends AbstractRepository
{
    public function findById(TagId $tagId): Tag|null
    {
        return $this->findByPK($tagId->value());
    }

    public function findByText(TagText $text): Tag|null
    {
        return $this->findOne(['text' => $text->value()]);
    }

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
}
