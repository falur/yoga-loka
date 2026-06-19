<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Dto;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, UserPublicProfileView>
 */
final class UserPublicProfileCollection extends Collection {}
