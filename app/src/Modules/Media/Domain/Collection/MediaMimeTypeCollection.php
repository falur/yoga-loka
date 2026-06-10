<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, MediaMimeType>
 */
final class MediaMimeTypeCollection extends Collection
{
    public function containsMimeType(MediaMimeType $mimeType): bool
    {
        return $this->contains(static fn(MediaMimeType $allowed): bool => $allowed->equals($mimeType));
    }
}
