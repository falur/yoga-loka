<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Resource;

use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class UnreadCountResource extends AbstractResource
{
    public function __construct(
        public int $count,
    ) {}
}
