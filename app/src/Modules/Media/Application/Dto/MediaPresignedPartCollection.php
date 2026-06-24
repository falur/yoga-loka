<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, MediaPresignedPart>
 */
final class MediaPresignedPartCollection extends TypedCollection {}
