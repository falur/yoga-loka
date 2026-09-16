<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Cycle;

use Cycle\Annotated\Annotation\Column;

/**
 * Cycle Entity не содержит бизнес-методов (cycle-entity.md), поэтому поля — обычные
 * публичные, без asymmetric visibility: {Name}Mapper::toCycleEntity() пишет значения
 * напрямую из уже готовых доменных createdAt/updatedAt (App\Shared\Domain\Trait\HasTimestamps),
 * а не через initializeTimestamps()/touch() — эти методы решают, когда обновлять updatedAt,
 * и это доменное решение, принятое до Mapper.
 */
trait HasTimestamps
{
    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    public \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
    public \DateTimeImmutable $updatedAt;
}
