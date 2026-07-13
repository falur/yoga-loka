<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\View;

/**
 * Read-model одной сессии пользователя для ответа API: проекция группы токенов одной сессии
 * (access + refresh). createdAt — самый ранний выпуск, expiresAt — самый поздний срок (refresh,
 * ~60 дней). ip/device — null, если устройство неизвестно. current — это текущая сессия запроса.
 * Собирается SessionViewAssembler.
 */
final readonly class SessionView
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $expiresAt,
        public string|null $ip,
        public string|null $device,
        public bool $current,
    ) {}
}
