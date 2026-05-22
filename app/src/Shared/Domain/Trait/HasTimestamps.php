<?php

declare(strict_types=1);

namespace App\Shared\Domain\Trait;

use Cycle\Annotated\Annotation\Column;

trait HasTimestamps
{
    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    public private(set) \DateTimeImmutable $createdAt;

    #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
    public private(set) \DateTimeImmutable $updatedAt;

    public function initializeTimestamps(?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touch(?\DateTimeImmutable $now = null): void
    {
        $this->updatedAt = $now ?? new \DateTimeImmutable();
    }
}
