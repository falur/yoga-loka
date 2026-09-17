<?php

declare(strict_types=1);

namespace App\Modules\User\Public\Dto;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * Набор публичных профилей, ключ — идентификатор пользователя: потребитель берёт готовый профиль по
 * идентификатору (->get($userId)) без отдельного запроса на каждого. Несуществующие пользователи в
 * набор не попадают — их идентификаторов в коллекции нет.
 *
 * @extends TypedCollection<string, UserProfileDto>
 */
final class UserProfileDtoCollection extends TypedCollection {}
