<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinition;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, NotificationTypeDefinition>
 */
final class NotificationTypeDefinitionCollection extends Collection {}
