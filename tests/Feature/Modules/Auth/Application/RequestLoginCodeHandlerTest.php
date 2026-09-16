<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Command\RequestLoginCode\RequestLoginCodeCommand;
use App\Modules\Auth\Application\Command\RequestLoginCode\RequestLoginCodeHandler;
use App\Modules\Auth\Public\Event\LoginCodeRequestedEvent;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use Psr\Log\NullLogger;
use Tests\Feature\Modules\Auth\Application\Fixture\RecordingOutboxEventStore;

final class RequestLoginCodeHandlerTest extends AuthApplicationTestCase
{
    public function testIssuesCodeAndQueuesEmailWithRequestLocale(): void
    {
        $outbox = new RecordingOutboxEventStore();

        $this->handler($outbox)->handle(new RequestLoginCodeCommand(email: 'user@example.com', requestLocale: 'ru'));

        self::assertCount(1, $outbox->messages);
        $message = $outbox->messages[0];
        self::assertInstanceOf(LoginCodeRequestedEvent::class, $message);
        self::assertSame('user@example.com', $message->email);
        self::assertSame('ru', $message->locale);
        self::assertMatchesRegularExpression('/^\d{6}$/', $message->code);

        $loginCode = $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('user@example.com'));
        self::assertInstanceOf(LoginCode::class, $loginCode);
        self::assertSame($this->secretHasher()->hash($message->code), $loginCode->codeHash->value());
    }

    public function testThrottlesRepeatedRequestWithinWindow(): void
    {
        $outbox = new RecordingOutboxEventStore();
        $requestLoginCodeHandler = $this->handler($outbox);

        $requestLoginCodeHandler->handle(new RequestLoginCodeCommand(email: 'user@example.com', requestLocale: 'ru'));
        $requestLoginCodeHandler->handle(new RequestLoginCodeCommand(email: 'user@example.com', requestLocale: 'ru'));

        self::assertCount(1, $outbox->messages);
    }

    public function testReplacesStaleCodeBeyondWindow(): void
    {
        $staleCode = $this->persistLoginCode(
            email: 'user@example.com',
            code: '111111',
            createdAt: (new \DateTimeImmutable())->sub(new \DateInterval('PT2M')),
        );
        $outbox = new RecordingOutboxEventStore();

        $this->handler($outbox)->handle(new RequestLoginCodeCommand(email: 'user@example.com', requestLocale: 'en'));

        self::assertCount(1, $outbox->messages);
        $activeCode = $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('user@example.com'));
        self::assertInstanceOf(LoginCode::class, $activeCode);
        self::assertFalse($staleCode->id->equals($activeCode->id));
    }

    private function handler(RecordingOutboxEventStore $outbox): RequestLoginCodeHandler
    {
        return new RequestLoginCodeHandler(
            loginCodeRepository: $this->loginCodeRepository(),
            secretHasher: $this->secretHasher(),
            integrationEventStore: $outbox,
            logger: new NullLogger(),
        );
    }
}
