<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, MediaPresignedPart>
 */
final class MediaPresignedPartCollection extends Collection {}
