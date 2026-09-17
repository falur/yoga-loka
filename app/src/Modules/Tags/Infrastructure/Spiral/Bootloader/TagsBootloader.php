<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Spiral\Bootloader;

use App\Modules\Tags\Domain\Repository\TagRepository;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Repository\CycleTagRepository;
use App\Modules\Tags\Infrastructure\Spiral\PublicApi\TagsProvider;
use App\Modules\Tags\Public\Contract\TagsContract;
use Cycle\Migrations\Config\MigrationConfig;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;

/**
 * Точка подключения модуля Tags к приложению: доменный интерфейс хранения связан со своей
 * Cycle-реализацией, публичный контракт модуля — со своим входным адаптером. Следующие волны
 * переезда добавят сюда конфигурацию модуля и переводы.
 *
 * Таблицу `tags` создаёт миграция этого каталога (`20260917.090000_0_create_tags_table.php`): все
 * изменения таблицы `tags` — миграции модуля Tags.
 */
final class TagsBootloader extends Bootloader
{
    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';

    protected const BINDINGS = [
        TagRepository::class => CycleTagRepository::class,
        TagsContract::class => TagsProvider::class,
    ];

    /** @param ConfiguratorInterface<object> $config */
    public function init(ConfiguratorInterface $config): void
    {
        // Каталог миграций модуля дописывается в общий механизм: файлы остаются внутри модуля,
        // а удаление модуля не оставляет миграций в чужих папках.
        $config->modify(
            section: MigrationConfig::CONFIG,
            patch: new Append(
                position: self::MIGRATION_VENDOR_DIRECTORIES,
                key: null,
                value: \sprintf(
                    '%s/Infrastructure/Persistence/Cycle/Migration',
                    \dirname(path: __DIR__, levels: 3),
                ),
            ),
        );
    }
}
