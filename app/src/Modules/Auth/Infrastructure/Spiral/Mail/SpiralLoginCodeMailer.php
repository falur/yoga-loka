<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Mail;

use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use Spiral\Mailer\MailerInterface;
use Spiral\Mailer\Message;

/**
 * Адаптер над Spiral\Mailer\MailerInterface для письма с кодом входа. Application передаёт уже
 * локализованные тему и тело, а здесь собирается Message с view `auth:login-code` (шаблон лежит в
 * Infrastructure/Spiral/Resources/views модуля, namespace `auth` регистрирует AuthBootloader) и выполняется отправка.
 * Сбой отправки (граница системы) всплывает выше — его обрабатывает SendLoginCodeJob.
 */
final readonly class SpiralLoginCodeMailer implements LoginCodeMailerContract
{
    private const string EMAIL_VIEW = 'auth:login-code';

    public function __construct(
        private MailerInterface $mailer,
    ) {}

    #[\Override]
    public function send(string $email, string $subject, string $body): void
    {
        $this->mailer->send(new Message(
            subject: self::EMAIL_VIEW,
            to: $email,
            data: [
                'subject' => $subject,
                'body' => $body,
            ],
        ));
    }
}
