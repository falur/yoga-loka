<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Entity\Media;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Media>
 */
final class MediaCollection extends Collection {}
