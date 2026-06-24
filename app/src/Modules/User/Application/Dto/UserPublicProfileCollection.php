<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Dto;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, UserPublicProfileView>
 */
final class UserPublicProfileCollection extends TypedCollection {}
