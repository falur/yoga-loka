<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Notification\MarkAllNotificationsRead;

use App\Modules\Notifications\Application\Contract\MarkAllNotificationsReadContract;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Отмечает все непрочитанные уведомления получателя прочитанными одним UPDATE через порт
 * массовой отметки. Сущности в память не грузятся — число затронутых строк не ограничено сверху,
 * поэтому загрузка коллекции была бы неоправданной по памяти.
 */
final readonly class MarkAllNotificationsReadHandler
{
    public function __construct(
        private MarkAllNotificationsReadContract $markAllNotificationsRead,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(MarkAllNotificationsReadCommand $command): void
    {
        $markedCount = $this->markAllNotificationsRead->markAllReadForRecipient(
            userId: UserId::fromString($command->userId),
            readAt: new \DateTimeImmutable(),
        );

        $this->logger->debug(message: 'Все уведомления отмечены прочитанными.', context: [
            'userId' => $command->userId,
            'count' => $markedCount,
        ]);
    }
}
