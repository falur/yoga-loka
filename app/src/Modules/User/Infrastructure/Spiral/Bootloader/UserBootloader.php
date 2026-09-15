<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Spiral\Bootloader;

use App\Modules\User\Infrastructure\Spiral\PublicApi\UserProvider;
use App\Modules\User\Public\Contract\UserContract;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Точка подключения модуля User к приложению: публичный контракт модуля связан со своим входным
 * адаптером. Следующие волны переезда добавят сюда конфигурацию модуля, путь его миграций и переводы.
 */
final class UserBootloader extends Bootloader
{
    protected const BINDINGS = [
        UserContract::class => UserProvider::class,
    ];
}
