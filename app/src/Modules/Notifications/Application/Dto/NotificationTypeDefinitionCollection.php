<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, NotificationTypeDefinition>
 */
final class NotificationTypeDefinitionCollection extends TypedCollection {}
