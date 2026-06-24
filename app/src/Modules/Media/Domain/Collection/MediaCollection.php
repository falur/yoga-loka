<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Entity\Media;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, Media>
 */
final class MediaCollection extends TypedCollection {}
