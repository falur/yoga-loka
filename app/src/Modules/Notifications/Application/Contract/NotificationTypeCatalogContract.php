<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

use App\Modules\Notifications\Application\Dto\NotificationTypeDefinitionCollection;
use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

/**
 * Внутренний каталог определений видов уведомлений — единый источник правды о видах и их дефолтных
 * каналах. Регистрацию соседям публикует NotificationTypeRegistryContract из Public; чтение
 * (перебор видов и поиск по коду) остаётся внутри модуля.
 */
interface NotificationTypeCatalogContract
{
    public function register(NotificationTypeDefinition ...$definitions): void;

    public function all(): NotificationTypeDefinitionCollection;

    /**
     * Возвращает определение по коду; на незарегистрированный вид бросает исключение (fail-fast).
     */
    public function get(NotificationTypeCode $code): NotificationTypeDefinition;
}
