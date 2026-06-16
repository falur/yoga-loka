<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaIsImage;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;

final readonly class CheckMediaIsImageHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {}

    public function handle(CheckMediaIsImageQuery $query): bool
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId));

        return $media !== null && $media->type === MediaType::Image;
    }
}
