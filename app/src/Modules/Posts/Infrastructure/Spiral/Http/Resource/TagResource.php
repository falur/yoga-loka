<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Resource;

use App\Modules\Posts\Application\Result\TagResult;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

final readonly class TagResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}

    public static function fromResult(TagResult $tag): self
    {
        return new self(id: $tag->id, text: $tag->text);
    }
}
