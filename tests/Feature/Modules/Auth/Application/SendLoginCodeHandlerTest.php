<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeCommand;
use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeHandler;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use Spiral\Translator\TranslatorInterface;
use Tests\Feature\Modules\Auth\Application\Fixture\RecordingLoginCodeMailer;
use Tests\TestCase;

final class SendLoginCodeHandlerTest extends TestCase
{
    public function testSendsRussianEmailWithCode(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->handler($loginCodeMailer)->handle(new SendLoginCodeCommand(email: 'user@example.com', code: '123456', locale: 'ru'));

        self::assertCount(1, $loginCodeMailer->sentEmails);
        $sentEmail = $loginCodeMailer->sentEmails[0];
        self::assertSame('user@example.com', $sentEmail->email);
        self::assertSame('Код для входа в YogaLoka', $sentEmail->subject);
        self::assertStringContainsString('123456', $sentEmail->body);
    }

    public function testSendsEnglishEmail(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->handler($loginCodeMailer)->handle(new SendLoginCodeCommand(email: 'user@example.com', code: '654321', locale: 'en'));

        $sentEmail = $loginCodeMailer->sentEmails[0];
        self::assertSame('Your YogaLoka login code', $sentEmail->subject);
        self::assertStringContainsString('654321', $sentEmail->body);
    }

    public function testFallsBackToDefaultLocaleForUnsupportedValue(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->handler($loginCodeMailer)->handle(new SendLoginCodeCommand(email: 'user@example.com', code: '111111', locale: 'fr'));

        $sentEmail = $loginCodeMailer->sentEmails[0];
        self::assertSame('Код для входа в YogaLoka', $sentEmail->subject);
    }

    private function handler(RecordingLoginCodeMailer $loginCodeMailer): SendLoginCodeHandler
    {
        return new SendLoginCodeHandler(
            loginCodeMailer: $loginCodeMailer,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            localeConfig: $this->getContainer()->get(LocaleConfig::class),
        );
    }
}
