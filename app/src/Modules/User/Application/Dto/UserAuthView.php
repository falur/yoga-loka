<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Dto;

/**
 * Application-DTO для границы с модулем Auth: ровно то, что Auth знает о пользователе —
 * его идентификатор и право входа. Entity наружу не отдаём, чтобы держать границу модулей.
 */
final readonly class UserAuthView
{
    public function __construct(
        public string $userId,
        public bool $canSignIn,
    ) {}
}
