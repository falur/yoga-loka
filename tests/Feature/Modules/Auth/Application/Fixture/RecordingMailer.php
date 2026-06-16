<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application\Fixture;

use Spiral\Mailer\MailerInterface;
use Spiral\Mailer\MessageInterface;

/**
 * Записывающий mailer для проверки собранного письма без реальной отправки и рендера view.
 */
final class RecordingMailer implements MailerInterface
{
    /**
     * @var list<MessageInterface>
     */
    public array $messages = [];

    #[\Override]
    public function send(MessageInterface ...$message): void
    {
        foreach ($message as $singleMessage) {
            $this->messages[] = $singleMessage;
        }
    }
}
