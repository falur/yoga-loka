<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Bootloader;

use App\Modules\Media\Public\Event\MediaDeletedEvent;
use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;
use App\Modules\Posts\Application\Command\CreatePost\PostNotificationType;
use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Application\Contract\DetachMediaAttachmentsContract;
use App\Modules\Posts\Application\Contract\PostReader;
use App\Modules\Posts\Application\Contract\PostViewerReader;
use App\Modules\Posts\Application\Contract\TranslatorContract;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostBlockRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\CycleDetachMediaAttachments;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Read\CycleCommentViewerReader;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Read\CyclePostReader;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Read\CyclePostViewerReader;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CycleCommentRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CyclePostBlockRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CyclePostRepository;
use App\Modules\Posts\Infrastructure\Spiral\Adapter\SpiralTranslator;
use App\Modules\Posts\Infrastructure\Spiral\Job\DetachDeletedMediaJob;
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
 * регистрируются разом через cases(). Порт массовой записи DetachMediaAttachmentsContract — с
 * прямой Cycle-реализацией, а в boot() регистрируется маршрут «MediaDeletedEvent (Media) ->
 * DetachDeletedMediaJob»: межмодульные внешние ключи на media не используются как основа
 * согласованности (docs/arch.md, «Владение данными»), поэтому Posts сам подписывается на факт
 * удаления медиа через его Public/Event и снимает свои вложения, ставшие невалидными. Регистрация
 * маршрута лежит здесь, а не в MediaBootloader: deptrac запрещает Infrastructure/Spiral одного
 * модуля зависеть от Infrastructure/Spiral (в том числе Job) другого — только потребитель, которому
 * разрешён импорт чужого Public, может связать чужое событие со своим Job.
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
        DetachMediaAttachmentsContract::class => CycleDetachMediaAttachments::class,
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

    public function boot(
        NotificationTypeRegistryContract $typeRegistry,
        IntegrationEventRoutingContract $integrationEventRouting,
    ): void {
        $typeRegistry->register(...PostNotificationType::cases());

        $integrationEventRouting->register(
            integrationEventClass: MediaDeletedEvent::class,
            jobClass: DetachDeletedMediaJob::class,
        );
    }
}
