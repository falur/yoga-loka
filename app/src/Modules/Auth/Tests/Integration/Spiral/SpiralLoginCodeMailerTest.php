<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Infrastructure\Spiral\Mail\SpiralLoginCodeMailer;
use Spiral\Mailer\MailerInterface;
use Spiral\Mailer\Message;
use Spiral\Views\ViewsInterface;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class SpiralLoginCodeMailerTest extends TestCase
{
    public function testBuildsLoginCodeMessageAndSends(): void
    {
        $recordingMailer = new RecordingMailer();

        new SpiralLoginCodeMailer($recordingMailer)->send(
            email: 'user@example.com',
            subject: 'Код для входа в YogaLoka',
            body: 'Ваш код: 123456',
        );

        self::assertCount(1, $recordingMailer->messages);
        $message = $recordingMailer->messages[0];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('auth:login-code', $message->getSubject());
        self::assertContains('user@example.com', $message->getTo());
        self::assertSame('Код для входа в YogaLoka', $message->getData()['subject']);
        self::assertSame('Ваш код: 123456', $message->getData()['body']);
    }

    public function testLoginCodeViewRendersFromAuthNamespace(): void
    {
        $email = new Email();

        $this->getContainer()->get(ViewsInterface::class)->get('auth:login-code')->render([
            '_msg_' => $email,
            'subject' => 'Код для входа в YogaLoka',
            'body' => 'Ваш код: 123456',
        ]);

        self::assertSame('Код для входа в YogaLoka', $email->getSubject());
        self::assertStringContainsString('123456', (string) $email->getTextBody());
    }

    public function testPropagatesMailerFailure(): void
    {
        $failingMailer = $this->createStub(MailerInterface::class);
        $failingMailer->method('send')->willThrowException(new \RuntimeException('smtp недоступен'));

        $this->expectException(\RuntimeException::class);

        new SpiralLoginCodeMailer($failingMailer)->send(
            email: 'user@example.com',
            subject: 'тема',
            body: 'тело',
        );
    }
}
