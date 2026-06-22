<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, MediaAudioConversion>
 */
final class MediaAudioConversionCollection extends Collection {}
