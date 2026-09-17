<?php

declare(strict_types=1);

namespace App\Modules\User\Public\Dto;

/**
 * Итог создания пользователя для соседа: только идентификатор. Полный профиль сосед дочитывает
 * отдельной операцией контракта, когда он ему нужен.
 */
final readonly class CreatedUserDto
{
    public function __construct(
        public string $userId,
    ) {}
}
