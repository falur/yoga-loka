<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Spiral\Bootloader;

use App\Modules\Access\Domain\Repository\PermissionRepository;
use App\Modules\Access\Domain\Repository\RoleRepository;
use App\Modules\Access\Domain\Repository\UserRoleRepository;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CyclePermissionRepository;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CycleRoleRepository;
use App\Modules\Access\Infrastructure\Persistence\Cycle\Repository\CycleUserRoleRepository;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Точка подключения модуля Access к приложению: доменные интерфейсы хранения трёх корней
 * агрегатов связаны со своими Cycle-реализациями. Связь роли с правом — внутренняя сущность
 * агрегата роли, поэтому собственного интерфейса у неё нет. Следующие волны переезда добавят
 * сюда конфигурацию модуля, путь его миграций, переводы и привязки контрактов к реализациям.
 */
final class AccessBootloader extends Bootloader
{
    protected const BINDINGS = [
        RoleRepository::class => CycleRoleRepository::class,
        PermissionRepository::class => CyclePermissionRepository::class,
        UserRoleRepository::class => CycleUserRoleRepository::class,
    ];
}
