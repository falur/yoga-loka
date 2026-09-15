<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Job;

use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeCommand;
use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeHandler;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job отправки письма с кодом: грузит LoginCodeRequested из outbox и
 * диспатчит SendLoginCodeCommand. Сбой отправки (граница системы) → WARN + RetryException,
 * чтобы письмо было повторено (статусы outbox правит общий queue interceptor).
 */
final class SendLoginCodeJob extends JobHandler
{
    private const string MAILER_FAILURE_MESSAGE = 'Не удалось отправить письмо с кодом входа.';

    public function invoke(
        OutboxQueueEnvelope $payload,
        string $id,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        SendLoginCodeHandler $sendLoginCodeHandler,
        LoggerInterface $logger,
    ): void {
        $loginCodeRequested = $outboxMessageLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedMessageClass: LoginCodeRequested::class,
        );

        try {
            $commandBus->dispatch(
                command: new SendLoginCodeCommand(
                    email: $loginCodeRequested->email,
                    code: $loginCodeRequested->code,
                    locale: $loginCodeRequested->locale,
                ),
                handler: $sendLoginCodeHandler->handle(...),
            );
        } catch (\Throwable $exception) {
            $logger->warning(message: self::MAILER_FAILURE_MESSAGE, context: [
                'email' => $loginCodeRequested->email,
                'jobId' => $id,
                'errorClass' => $exception::class,
            ]);

            throw new RetryException(reason: self::MAILER_FAILURE_MESSAGE);
        }
    }
}
