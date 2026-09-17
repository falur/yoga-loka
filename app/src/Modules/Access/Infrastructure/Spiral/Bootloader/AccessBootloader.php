<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Spiral\Bootloader;

use App\Modules\Access\Domain\Repository\PermissionRepository;
use App\Modules\Access\Domain\Repository\RoleRepository;
use App\Modules\Access\Domain\Repository\UserRoleRepository;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CyclePermissionRepository;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CycleRoleRepository;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CycleUserRoleRepository;
use Cycle\Migrations\Config\MigrationConfig;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;

/**
 * Точка подключения модуля Access к приложению: доменные интерфейсы хранения трёх корней
 * агрегатов связаны со своими Cycle-реализациями. Связь роли с правом — внутренняя сущность
 * агрегата роли, поэтому собственного интерфейса у неё нет. Следующие волны переезда добавят
 * сюда конфигурацию модуля и переводы.
 */
final class AccessBootloader extends Bootloader
{
    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';

    protected const BINDINGS = [
        RoleRepository::class => CycleRoleRepository::class,
        PermissionRepository::class => CyclePermissionRepository::class,
        UserRoleRepository::class => CycleUserRoleRepository::class,
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
