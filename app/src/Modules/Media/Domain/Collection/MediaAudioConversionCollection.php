<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, MediaAudioConversion>
 */
final class MediaAudioConversionCollection extends TypedCollection {}
