<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaExists;

use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;

final readonly class CheckMediaExistsHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {}

    public function handle(CheckMediaExistsQuery $query): bool
    {
        return $this->mediaRepository->findById(MediaId::fromString($query->mediaId)) !== null;
    }
}
