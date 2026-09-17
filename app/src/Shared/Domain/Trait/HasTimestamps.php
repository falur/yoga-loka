<?php

declare(strict_types=1);

namespace App\Shared\Domain\Trait;

trait HasTimestamps
{
    public private(set) \DateTimeImmutable $createdAt;

    public private(set) \DateTimeImmutable $updatedAt;

    public function initializeTimestamps(\DateTimeImmutable|null $now = null): void
    {
        $now ??= new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touch(\DateTimeImmutable|null $now = null): void
    {
        $this->updatedAt = $now ?? new \DateTimeImmutable();
    }
}
