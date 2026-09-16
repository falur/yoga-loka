<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Spiral\Bootloader;

use App\Modules\User\Domain\Repository\ReservedNicknameRepository;
use App\Modules\User\Domain\Repository\UserBanRepository;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Modules\User\Infrastructure\Persistence\Cycle\Repository\CycleReservedNicknameRepository;
use App\Modules\User\Infrastructure\Persistence\Cycle\Repository\CycleUserBanRepository;
use App\Modules\User\Infrastructure\Persistence\Cycle\Repository\CycleUserRepository;
use App\Modules\User\Infrastructure\Spiral\PublicApi\UserProvider;
use App\Modules\User\Public\Contract\UserContract;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Точка подключения модуля User к приложению: доменные интерфейсы хранения связаны со своими
 * Cycle-реализациями, публичный контракт модуля — со своим входным адаптером. Следующие волны
 * переезда добавят сюда конфигурацию модуля, путь его миграций и переводы.
 */
final class UserBootloader extends Bootloader
{
    protected const BINDINGS = [
        UserRepository::class => CycleUserRepository::class,
        UserBanRepository::class => CycleUserBanRepository::class,
        ReservedNicknameRepository::class => CycleReservedNicknameRepository::class,
        UserContract::class => UserProvider::class,
    ];
}
