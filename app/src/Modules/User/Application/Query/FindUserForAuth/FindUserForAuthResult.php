<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\FindUserForAuth;

/**
 * Результат FindUserForAuthQuery: ровно то, что Auth знает о пользователе — его идентификатор и
 * право входа. Entity наружу не отдаём, чтобы держать границу модулей. Используется только этим
 * Query, поэтому лежит рядом с ним, а не в общем Application/Result.
 */
final readonly class FindUserForAuthResult
{
    public function __construct(
        public string $userId,
        public bool $canSignIn,
    ) {}
}
