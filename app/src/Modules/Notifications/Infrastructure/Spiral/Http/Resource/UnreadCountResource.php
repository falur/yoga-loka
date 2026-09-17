<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Resource;

use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

final readonly class UnreadCountResource extends AbstractResource
{
    public function __construct(
        public int $count,
    ) {}
}
