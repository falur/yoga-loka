<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Centrifugo;

use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Infrastructure\Exception\CentrifugoPresenceException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;

/**
 * Реализация проверки онлайн-статуса через presence-статистику Centrifugo по персональному каналу.
 */
final readonly class CentrifugoOnlinePresence implements OnlinePresenceContract
{
    public function __construct(
        private CentrifugoClient $centrifugoClient,
        private LoggerInterface $logger,
    ) {}

    public function isOnline(UserId $userId): bool
    {
        // Формат канала синхронен с PublishRealtimeNotificationHandler ('personal:#user_%s').
        // userId — нормализованный UUID v7 в нижнем регистре, поэтому строка байт-в-байт совпадает
        // с realtime-каналом (там используется сырой $command->userId, см. инвариант в плане).
        $channel = \sprintf('personal:#user_%s', $userId->value());

        try {
            return $this->centrifugoClient->presenceStats($channel)->numClients > 0;
        } catch (CentrifugoPresenceException $exception) {
            // fail-open: не удалось проверить presence → считаем «не онлайн», push будет отправлен.
            $this->logger->warning(message: 'Не удалось проверить онлайн-статус получателя, push будет отправлен.', context: [
                'userId' => $userId->value(),
                'errorClass' => $exception::class,
            ]);

            return false;
        }
    }
}
