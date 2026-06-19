<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Resource;

use App\Modules\Posts\Application\View\TagView;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class TagResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}

    public static function fromView(TagView $tag): self
    {
        return new self(id: $tag->id, text: $tag->text);
    }
}
