<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Spiral\Bootloader;

use App\Modules\Tags\Infrastructure\Spiral\PublicApi\TagsProvider;
use App\Modules\Tags\Public\Contract\TagsContract;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Точка подключения модуля Tags к приложению: публичный контракт модуля связан со своим входным
 * адаптером. Следующие волны переезда добавят сюда конфигурацию модуля, путь его миграций и переводы.
 */
final class TagsBootloader extends Bootloader
{
    protected const BINDINGS = [
        TagsContract::class => TagsProvider::class,
    ];
}
