<?php

declare(strict_types=1);

namespace App\Modules\Tags\Domain\Repository;

use App\Modules\Tags\Domain\Collection\TagCollection;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagId;
use App\Modules\Tags\Domain\ValueObject\TagText;

/**
 * Хранение тегов. Корень агрегата — Tag, внутренних сущностей у него нет.
 */
interface TagRepository
{
    public function findById(TagId $tagId): Tag|null;

    public function findByText(TagText $text): Tag|null;

    public function findByTexts(TagText ...$tagTexts): TagCollection;

    public function findByIds(TagId ...$tagIds): TagCollection;

    /**
     * Сохраняет набор тегов одним прогоном. Пустой набор ничего не пишет: сценарий разрешения
     * тегов вызывает сохранение и тогда, когда все запрошенные теги уже существуют.
     */
    public function saveAll(TagCollection $tags): void;
}
