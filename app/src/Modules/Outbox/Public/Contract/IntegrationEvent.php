<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Public\Contract;

/**
 * Маркер интеграционного события: модуль-издатель помечает им сообщение, которое кладёт в outbox,
 * а Outbox по этому маркеру сериализует payload, ищет Job и восстанавливает событие для потребителя.
 */
interface IntegrationEvent {}
