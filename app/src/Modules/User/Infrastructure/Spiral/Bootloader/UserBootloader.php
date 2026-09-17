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
use Cycle\Migrations\Config\MigrationConfig;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;

/**
 * Точка подключения модуля User к приложению: доменные интерфейсы хранения связаны со своими
 * Cycle-реализациями, публичный контракт модуля — со своим входным адаптером. Следующие волны
 * переезда добавят сюда конфигурацию модуля.
 */
final class UserBootloader extends Bootloader
{
    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';

    protected const BINDINGS = [
        UserRepository::class => CycleUserRepository::class,
        UserBanRepository::class => CycleUserBanRepository::class,
        ReservedNicknameRepository::class => CycleReservedNicknameRepository::class,
        UserContract::class => UserProvider::class,
    ];

    /** @param ConfiguratorInterface<object> $config */
    public function init(ConfiguratorInterface $config, I18nBootloader $i18n): void
    {
        // Переводы модуля лежат внутри модуля: удаление модуля не оставляет переводов в чужих папках.
        $i18n->addDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Resources/locale', \dirname(path: __DIR__, levels: 3)),
        );

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
