<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Spiral\Bootloader;

use App\Modules\Tags\Domain\Repository\TagRepository;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Repository\CycleTagRepository;
use App\Modules\Tags\Infrastructure\Spiral\PublicApi\TagsProvider;
use App\Modules\Tags\Public\Contract\TagsContract;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Точка подключения модуля Tags к приложению: доменный интерфейс хранения связан со своей
 * Cycle-реализацией, публичный контракт модуля — со своим входным адаптером. Следующие волны
 * переезда добавят сюда конфигурацию модуля, путь его миграций и переводы.
 */
final class TagsBootloader extends Bootloader
{
    protected const BINDINGS = [
        TagRepository::class => CycleTagRepository::class,
        TagsContract::class => TagsProvider::class,
    ];
}
