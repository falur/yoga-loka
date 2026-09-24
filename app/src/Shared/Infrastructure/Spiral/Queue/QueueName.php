<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Queue;

/**
 * Имена очередей приложения.
 *
 * Значение нужно и конфигурации транспорта (`app/config/queue.php`, `docker/rr/http-jobs.yaml`),
 * и маршрутам событий в разных модулях, поэтому владельца среди модулей у перечисления нет и
 * оно живёт в общей части Spiral-инфраструктуры.
 */
enum QueueName: string
{
    case Mail = 'mail';
    case Media = 'media';
    case Notifications = 'notifications';
}
