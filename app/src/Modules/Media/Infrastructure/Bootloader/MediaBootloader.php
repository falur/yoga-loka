<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Bootloader;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Infrastructure\FileService\ConfiguredS3ClientProvider;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaVideoProcessor;
use App\Modules\Media\Infrastructure\FileService\ImagickMediaImageProcessor;
use App\Modules\Media\Infrastructure\FileService\S3ClientProvider;
use App\Modules\Media\Infrastructure\FileService\S3MediaFileService;
use App\Modules\Media\Presentation\Job\ProcessMediaJob;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use Spiral\Boot\Bootloader\Bootloader;

final class MediaBootloader extends Bootloader
{
    protected const BINDINGS = [
        MediaFileServiceContract::class => S3MediaFileService::class,
        MediaImageProcessorContract::class => ImagickMediaImageProcessor::class,
        MediaVideoProcessorContract::class => FfmpegMediaVideoProcessor::class,
        MediaAudioProcessorContract::class => FfmpegMediaAudioProcessor::class,
        S3ClientProvider::class => ConfiguredS3ClientProvider::class,
    ];

    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: MediaUploaded::class,
            outboxJobClass: ProcessMediaJob::class,
        );
    }
}
