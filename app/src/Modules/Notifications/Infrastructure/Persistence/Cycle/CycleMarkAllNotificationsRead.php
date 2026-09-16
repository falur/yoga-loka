<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle;

use App\Modules\Notifications\Application\Contract\MarkAllNotificationsReadContract;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\DatabaseDateTimeFormat;
use App\Shared\Infrastructure\Persistence\Cycle\SetBasedWrite;
use Cycle\Database\DatabaseInterface;

/**
 * Массовая отметка уведомлений получателя прочитанными одним UPDATE. Реализует маркер
 * SetBasedWrite — только здесь PHPStan разрешает прямой UPDATE мимо EntityManager. Не использовать
 * для одиночных изменений: одно уведомление меняется доменным методом и сохраняется через
 * NotificationRepository.
 *
 * ВНИМАНИЕ: запись идёт в обход identity map ORM. Если в этом же запросе те же строки загружены
 * как Entity, копии в памяти станут устаревшими. Для отметки всех прочитанными это безопасно —
 * сущности после вызова не используются.
 */
final readonly class CycleMarkAllNotificationsRead implements MarkAllNotificationsReadContract, SetBasedWrite
{
    public function __construct(
        private DatabaseInterface $database,
    ) {}

    public function markAllReadForRecipient(UserId $userId, \DateTimeImmutable $readAt): int
    {
        return $this->database
            ->update('notifications')
            ->set(column: 'read_at', value: $readAt->format(DatabaseDateTimeFormat::WITH_MICROSECONDS))
            ->where('user_id', $userId->value())
            ->where('read_at', '=', null)
            ->run();
    }
}
