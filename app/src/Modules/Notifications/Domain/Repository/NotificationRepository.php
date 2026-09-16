<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationId;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение строк инбокса. Корень агрегата — Notification, внутренних сущностей у него нет.
 * Массовая отметка прочтения здесь не живёт: у неё свой порт в Application/Contract.
 */
interface NotificationRepository
{
    /**
     * Уведомление, уже созданное по этому событию рассылки, — признак повторной доставки.
     */
    public function findByOutboxId(NotificationOutboxId $outboxId): Notification|null;

    /**
     * Уведомление получателя по его идентификатору. Чужое уведомление даёт null.
     */
    public function findByIdForRecipient(NotificationId $id, UserId $userId): Notification|null;

    /**
     * Страница инбокса получателя. Вызывающий Query запрашивает limit+1 строк, чтобы вычислить
     * курсор следующей страницы.
     */
    public function findPageForRecipient(UserId $userId, NotificationId|null $cursor, int $limit): NotificationCollection;

    /**
     * Число непрочитанных уведомлений получателя.
     */
    public function countUnreadForRecipient(UserId $userId): int;

    /**
     * Сохраняет уведомление своим прогоном: вместе с ним в базу уходит всё, что уже поставлено
     * в текущую запись.
     */
    public function save(Notification $notification): void;

    /**
     * Сохраняет набор уведомлений одним прогоном на весь набор, а не по прогону на уведомление.
     * Тем же прогоном в базу уходит всё, что уже поставлено в текущую запись, поэтому пустой
     * набор завершает запись сценария, ничего не добавляя от себя.
     */
    public function saveAll(NotificationCollection $notifications): void;
}
