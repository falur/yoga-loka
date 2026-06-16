<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application\Fixture;

/**
 * Снимок собранного письма с кодом входа для проверок в тестах: адрес, тема и тело.
 */
final readonly class SentLoginCodeEmail
{
    public function __construct(
        public string $email,
        public string $subject,
        public string $body,
    ) {}
}
