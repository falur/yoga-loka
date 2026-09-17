<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Spiral\Bootloader;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaUploadPlannerContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Repository\CycleMediaRepository;
use App\Modules\Media\Infrastructure\Spiral\PublicApi\MediaProvider;
use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Event\MediaUploadedEvent;
use App\Modules\Media\Infrastructure\Storage\ConfiguredS3ClientProvider;
use App\Modules\Media\Infrastructure\Ffmpeg\FfmpegMediaAudioProcessor;
use App\Modules\Media\Infrastructure\Ffmpeg\FfmpegMediaVideoProcessor;
use App\Modules\Media\Infrastructure\Imagick\ImagickMediaImageProcessor;
use App\Modules\Media\Infrastructure\Storage\MediaUploadPlanner;
use App\Modules\Media\Infrastructure\Storage\MediaUrlService;
use App\Modules\Media\Infrastructure\Storage\S3ClientProvider;
use App\Modules\Media\Infrastructure\Storage\S3MediaFileService;
use App\Modules\Media\Infrastructure\Spiral\Job\ProcessMediaJob;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;
use App\Shared\Infrastructure\Spiral\Bootloader\ConfigBootloader;
use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;
use Cycle\Migrations\Config\MigrationConfig;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Storage\Config\StorageConfig as SpiralStorageConfig;

final class MediaBootloader extends Bootloader
{
    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';
    private const string STORAGE_BUCKETS_POSITION = 'buckets';

    protected const BINDINGS = [
        MediaRepository::class => CycleMediaRepository::class,
        MediaFileServiceContract::class => S3MediaFileService::class,
        MediaUrlServiceContract::class => MediaUrlService::class,
        MediaUploadPlannerContract::class => MediaUploadPlanner::class,
        MediaImageProcessorContract::class => ImagickMediaImageProcessor::class,
        MediaVideoProcessorContract::class => FfmpegMediaVideoProcessor::class,
        MediaAudioProcessorContract::class => FfmpegMediaAudioProcessor::class,
        S3ClientProvider::class => ConfiguredS3ClientProvider::class,
        MediaContract::class => MediaProvider::class,
    ];

    /** @param ConfiguratorInterface<object> $config */
    public function init(
        ConfiguratorInterface $config,
        I18nBootloader $i18n,
        ConfigBootloader $configBootloader,
    ): void {
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

        // Типизированный конфиг модуля лежит внутри модуля: удаление модуля не оставляет
        // конфигурации в чужих папках.
        $configBootloader->addConfigurationDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Configuration', \dirname(path: __DIR__, levels: 3)),
        );
        $config->setDefaults(
            section: 'media',
            data: ConfigArrayFile::read(path: \sprintf(
                '%s/Infrastructure/Spiral/Configuration/media.php',
                \dirname(path: __DIR__, levels: 3),
            )),
        );

        // Бакеты модуля остаются внутри модуля: дописываем их в общую секцию Spiral 'storage'
        // (позиция 'buckets') вместо общего app/config/storage.php. Порядок ЧТЕНИЯ готового
        // StorageInterface/StorageConfig действительно не зависит от порядка bootloader-ов (резолвится
        // лениво на реальном запросе, см. Spiral\Storage\Bootloader\StorageBootloader::init()) — но
        // для самого этого modify() порядок bootloader-ов в Kernel ВАЖЕН: Spiral\Storage\Bootloader\
        // StorageBootloader должен идти раньше MediaBootloader, поскольку его init() вызывает
        // setDefaults('storage', ...), а setDefaults() бросает ConfigDeliveredException, если секция
        // уже была прочитана — а modify() (этот вызов) читает секцию через getConfig() и кэширует её.
        // Если бы MediaBootloader::init() выполнился раньше StorageBootloader::init(), последующий
        // setDefaults() упал бы при старте приложения.
        foreach (ConfigArrayFile::read(path: \sprintf(
            '%s/Infrastructure/Spiral/Configuration/storage.php',
            \dirname(path: __DIR__, levels: 3),
        )) as $bucketName => $bucketConfig) {
            $config->modify(
                section: SpiralStorageConfig::CONFIG,
                patch: new Append(position: self::STORAGE_BUCKETS_POSITION, key: $bucketName, value: $bucketConfig),
            );
        }
    }

    public function boot(IntegrationEventRoutingContract $integrationEventRouting): void
    {
        $integrationEventRouting->register(
            integrationEventClass: MediaUploadedEvent::class,
            jobClass: ProcessMediaJob::class,
        );
    }
}
