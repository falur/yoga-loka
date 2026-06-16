<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application\Fixture;

use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;

/**
 * Записывающий дублёр LoginCodeMailerContract: изолирует Auth/Application от framework-mailer и
 * сохраняет собранные письма (email, тема, тело) для проверки в тестах сценария.
 */
final class RecordingLoginCodeMailer implements LoginCodeMailerContract
{
    /**
     * @var list<SentLoginCodeEmail>
     */
    public array $sentEmails = [];

    #[\Override]
    public function send(string $email, string $subject, string $body): void
    {
        $this->sentEmails[] = new SentLoginCodeEmail(email: $email, subject: $subject, body: $body);
    }
}
