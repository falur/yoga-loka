<?php

declare(strict_types=1);

namespace App\Modules\User\Public\Dto;

/**
 * Ровно то, что модуль входа знает о пользователе: его идентификатор и право входа. Причину запрета
 * (бан, удаление, неподтверждённый email) наружу не отдаём — она принадлежит User.
 */
final readonly class UserSignInDto
{
    public function __construct(
        public string $userId,
        public bool $canSignIn,
    ) {}
}
