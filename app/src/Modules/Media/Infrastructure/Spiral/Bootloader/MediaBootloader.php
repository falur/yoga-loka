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
use Spiral\Boot\Bootloader\Bootloader;

final class MediaBootloader extends Bootloader
{
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

    public function boot(IntegrationEventRoutingContract $integrationEventRouting): void
    {
        $integrationEventRouting->register(
            integrationEventClass: MediaUploadedEvent::class,
            jobClass: ProcessMediaJob::class,
        );
    }
}
