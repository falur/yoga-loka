<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Contract;

/**
 * Доменная граница отправки письма с кодом входа для Application-сценария. Application собирает
 * локализованные тему и тело, а конкретная отправка через framework-mailer инкапсулирована в
 * инфраструктурной реализации этого контракта.
 */
interface LoginCodeMailerContract
{
    public function send(string $email, string $subject, string $body): void;
}
