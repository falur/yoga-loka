<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Bootloader;

use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Posts\Application\Command\CreatePost\PostNotificationType;
use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Application\Contract\PostReader;
use App\Modules\Posts\Application\Contract\PostViewerReader;
use App\Modules\Posts\Application\Contract\TranslatorContract;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostBlockRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Read\CycleCommentViewerReader;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Read\CyclePostReader;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Read\CyclePostViewerReader;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CycleCommentRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CyclePostBlockRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CyclePostRepository;
use App\Modules\Posts\Infrastructure\Spiral\Translation\SpiralTranslator;
use Cycle\Migrations\Config\MigrationConfig;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;

/**
 * Бутлоадер модуля Posts. Связывает три доменных интерфейса хранения (Post, Comment, PostBlock —
 * три агрегата модуля) с их Cycle-реализациями, три Reader (страница ленты и флаги «оценил я») с их
 * Cycle-реализациями, порт перевода — с адаптером поверх Spiral\Translator, и регистрирует виды
 * уведомлений модуля через публичный контракт Notifications: реестр — синглтон, накапливающий
 * регистрации модулей-источников, поэтому регистрация делается в boot() (после поднятия
 * NotificationsBootloader в Kernel). Все виды модуля — один enum PostNotificationType,
 * регистрируются разом через cases().
 *
 * Историческое исключение из правила «миграция меняет только свои таблицы»: файл
 * `20260617.160942_0_create_posts_domain_tables.php` в каталоге миграций этого модуля создаёт
 * девять таблиц Posts и ещё одну — `tags` модуля Tags. Файл уже применён, и правило «не редактируй
 * применённую миграцию» (`docs/rules.md`) запрещает его разрезать на две миграции по владельцу.
 * Он лежит в Posts, а не в Tags, потому что здесь — девять таблиц из десяти: это более честный
 * выбор каталога для уже созданного файла, чем каталог модуля с одной таблицей из десяти. Создание
 * `tags` в этом файле остаётся историческим артефактом; все будущие изменения таблицы `tags` —
 * миграции модуля Tags.
 */
final class PostsBootloader extends Bootloader
{
    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';

    protected const BINDINGS = [
        PostRepository::class => CyclePostRepository::class,
        CommentRepository::class => CycleCommentRepository::class,
        PostBlockRepository::class => CyclePostBlockRepository::class,
        PostReader::class => CyclePostReader::class,
        PostViewerReader::class => CyclePostViewerReader::class,
        CommentViewerReader::class => CycleCommentViewerReader::class,
        TranslatorContract::class => SpiralTranslator::class,
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

    public function boot(NotificationTypeRegistryContract $typeRegistry): void
    {
        $typeRegistry->register(...PostNotificationType::cases());
    }
}
