<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeHandler;
use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Application\Contract\TranslatorContract;
use App\Modules\Auth\Public\Event\LoginCodeRequestedEvent;
use App\Modules\Auth\Infrastructure\Spiral\Job\SendLoginCodeJob;
use App\Shared\Domain\Locale\LocaleResolver;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class SendLoginCodeJobTest extends TestCase
{
    /** Идентификатор доставки: статусами доставки владеет интерсептор пакета, не Job. */
    private const string DELIVERY_ID = '0190f3b1-0000-7000-8000-0000000000de';

    public function testLoadsMessageAndSendsEmail(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->job()->invoke(
            outboxDeliveryId: self::DELIVERY_ID,
            outboxMessageLoader: $this->loaderReturning(),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendLoginCodeHandler: $this->handler($loginCodeMailer),
            logger: new NullLogger(),
        );

        self::assertCount(1, $loginCodeMailer->sentEmails);
        self::assertStringContainsString('123456', $loginCodeMailer->sentEmails[0]->body);
    }

    public function testRetriesWhenMailerFails(): void
    {
        $failingMailer = $this->createStub(LoginCodeMailerContract::class);
        $failingMailer->method('send')->willThrowException(new \RuntimeException('smtp недоступен'));

        $this->expectException(RetryableOutboxException::class);

        $this->job()->invoke(
            outboxDeliveryId: self::DELIVERY_ID,
            outboxMessageLoader: $this->loaderReturning(),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendLoginCodeHandler: $this->handler($failingMailer),
            logger: new NullLogger(),
        );
    }

    private function loaderReturning(): OutboxMessageLoaderContract
    {
        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new LoginCodeRequestedEvent(
            email: 'user@example.com',
            code: '123456',
            locale: 'ru',
        ));

        return $loader;
    }

    private function handler(LoginCodeMailerContract $loginCodeMailer): SendLoginCodeHandler
    {
        return new SendLoginCodeHandler(
            loginCodeMailer: $loginCodeMailer,
            translator: $this->getContainer()->get(TranslatorContract::class),
            localeResolver: $this->getContainer()->get(LocaleResolver::class),
        );
    }

    private function job(): SendLoginCodeJob
    {
        return $this->getContainer()->get(SendLoginCodeJob::class);
    }
}
