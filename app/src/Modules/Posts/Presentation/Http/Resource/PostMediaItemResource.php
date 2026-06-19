<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Resource;

use App\Modules\Posts\Application\View\PostMediaItemView;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class PostMediaItemResource extends AbstractResource
{
    public function __construct(
        public string $mediaId,
        public string $url,
        public int $position,
    ) {}

    public static function fromView(PostMediaItemView $media): self
    {
        return new self(
            mediaId: $media->mediaId,
            url: $media->url,
            position: $media->position,
        );
    }
}
