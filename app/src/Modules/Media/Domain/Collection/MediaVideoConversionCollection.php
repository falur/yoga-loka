<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, MediaVideoConversion>
 */
final class MediaVideoConversionCollection extends TypedCollection {}
