<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, MediaImageConversion>
 */
final class MediaImageConversionCollection extends TypedCollection {}
