<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Presentation;

use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeHandler;
use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Shared\Domain\Locale\LocaleResolver;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Spiral\Queue\Exception\RetryException;
use Spiral\Translator\TranslatorInterface;
use Tests\Feature\Modules\Auth\Application\Fixture\RecordingLoginCodeMailer;
use Tests\TestCase;

final class SendLoginCodeJobTest extends TestCase
{
    public function testLoadsMessageAndSendsEmail(): void
    {
        $loginCodeMailer = new RecordingLoginCodeMailer();

        $this->job()->invoke(
            payload: $this->envelope(),
            id: 'job-1',
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

        $this->expectException(RetryException::class);

        $this->job()->invoke(
            payload: $this->envelope(),
            id: 'job-1',
            outboxMessageLoader: $this->loaderReturning(),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendLoginCodeHandler: $this->handler($failingMailer),
            logger: new NullLogger(),
        );
    }

    private function loaderReturning(): OutboxMessageLoaderContract
    {
        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new LoginCodeRequested(
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
            translator: $this->getContainer()->get(TranslatorInterface::class),
            localeResolver: $this->getContainer()->get(LocaleResolver::class),
        );
    }

    private function job(): SendLoginCodeJob
    {
        return $this->getContainer()->get(SendLoginCodeJob::class);
    }

    private function envelope(): OutboxQueueEnvelope
    {
        return new OutboxQueueEnvelope(
            outboxEventId: OutboxEventId::fromString(Uuid::uuid7()->toString()),
            outboxEventType: OutboxEventType::fromString(LoginCodeRequested::class),
        );
    }
}
