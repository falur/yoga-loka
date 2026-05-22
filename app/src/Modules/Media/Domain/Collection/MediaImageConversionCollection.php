<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Entity\MediaImageConversion;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, MediaImageConversion>
 */
final class MediaImageConversionCollection extends Collection {}
