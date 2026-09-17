<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\PublicApi;

use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;

/**
 * Входной адаптер публичной регистрации видов: делегирует внутреннему каталогу, который остаётся
 * синглтоном и накапливает регистрации модулей-источников. Сценария здесь нет намеренно — это
 * настройка процесса при загрузке приложения, а не бизнес-операция с транзакцией.
 */
final readonly class NotificationTypeRegistryProvider implements NotificationTypeRegistryContract
{
    public function __construct(
        private NotificationTypeCatalogContract $typeCatalog,
    ) {}

    #[\Override]
    public function register(NotificationTypeDefinition ...$definitions): void
    {
        $this->typeCatalog->register(...$definitions);
    }
}
