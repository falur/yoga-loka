<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

use App\Modules\Notifications\Application\Dto\NotificationTypeDefinitionCollection;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

/**
 * Реестр определений видов уведомлений — единый источник правды о видах и их дефолтных каналах.
 * Модули-источники регистрируют свои определения; ядро только читает.
 */
interface NotificationTypeRegistryContract
{
    public function register(NotificationTypeDefinition ...$definitions): void;

    public function all(): NotificationTypeDefinitionCollection;

    /**
     * Возвращает определение по коду; на незарегистрированный вид бросает исключение (fail-fast).
     */
    public function get(NotificationTypeCode $code): NotificationTypeDefinition;
}
