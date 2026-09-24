<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Job;

use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeCommand;
use App\Modules\Auth\Application\Command\SendLoginCode\SendLoginCodeHandler;
use App\Modules\Auth\Public\Event\LoginCodeRequestedEvent;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Psr\Log\LoggerInterface;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job отправки письма с кодом: грузит LoginCodeRequestedEvent по идентификатору
 * доставки и диспатчит SendLoginCodeCommand. Сбой почтовой границы объясняется записью WARNING и
 * переводится в повторяемую ошибку outbox — статусы доставки правит интерсептор пакета. В контекст
 * записи идут только идентификатор доставки и класс ошибки: адрес получателя и код входа в журнал
 * не попадают.
 */
final class SendLoginCodeJob extends JobHandler
{
    private const string MAILER_FAILURE_MESSAGE = 'Не удалось отправить письмо с кодом входа.';

    public function invoke(
        string $outboxDeliveryId,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        SendLoginCodeHandler $sendLoginCodeHandler,
        LoggerInterface $logger,
    ): void {
        $loginCodeRequested = $outboxMessageLoader->load(
            outboxDeliveryId: $outboxDeliveryId,
            expectedMessageClass: LoginCodeRequestedEvent::class,
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
                'outboxDeliveryId' => $outboxDeliveryId,
                'errorClass' => $exception::class,
            ]);

            throw new RetryableOutboxException(message: self::MAILER_FAILURE_MESSAGE);
        }
    }
}
