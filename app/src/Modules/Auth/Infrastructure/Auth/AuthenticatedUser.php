<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Auth;

use App\Shared\Domain\ValueObject\UserId;

/**
 * Лёгкий актор HTTP-аутентификации: несёт только UserId из payload access-токена.
 * Тяжёлой проверки в БД на каждом запросе нет — бан отзывает токены, access короткоживущий.
 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public UserId $userId,
    ) {}
}
