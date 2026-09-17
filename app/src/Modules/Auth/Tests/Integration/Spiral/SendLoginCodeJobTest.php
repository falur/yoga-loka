<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeHandler;
use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Application\Contract\TranslatorContract;
use App\Modules\Auth\Public\Event\LoginCodeRequestedEvent;
use App\Modules\Auth\Infrastructure\Spiral\Job\SendLoginCodeJob;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Shared\Domain\Locale\LocaleResolver;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Spiral\Queue\Exception\RetryException;
use Tests\TestCase;

final class SendLoginCodeJobTest extends TestCase
{
    public function testLoadsMessageAndSendsEmail(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->job()->invoke(
            payload: $this->envelope(),
            id: 'job-1',
            integrationEventLoader: $this->loaderReturning(),
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

        $this->expectException(RetryException::class);

        $this->job()->invoke(
            payload: $this->envelope(),
            id: 'job-1',
            integrationEventLoader: $this->loaderReturning(),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendLoginCodeHandler: $this->handler($failingMailer),
            logger: new NullLogger(),
        );
    }

    private function loaderReturning(): IntegrationEventLoaderContract
    {
        $loader = $this->createStub(IntegrationEventLoaderContract::class);
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

    private function envelope(): OutboxEnvelopeDto
    {
        return new OutboxEnvelopeDto(
            outboxEventId: Uuid::uuid7()->toString(),
            outboxEventType: LoginCodeRequestedEvent::class,
        );
    }
}
