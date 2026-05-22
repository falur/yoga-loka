<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, MediaVideoConversion>
 */
final class MediaVideoConversionCollection extends Collection {}
