<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Contract\MediaUploadPlannerContract;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;

/**
 * Считает решения пайплайна загрузки из MediaConfig: срок staging-хранения, порог multipart, размер и
 * число частей. Лежит в Infrastructure и читает MediaConfig напрямую через конструктор (как
 * MediaUrlService), биндится const BINDINGS в MediaBootloader. Срок staging считается на момент вызова
 * внутри stagingExpiration(), иначе все загрузки получили бы один замороженный момент.
 *
 * partsCount() делит размер на переданный partSize->value(): вызывающий получает partSize() и отдаёт
 * его же в partsCount(), поэтому деление всегда идёт на тот размер части, который записывается в
 * MediaMultipartUpload (валидированный VO MediaMultipartPartSize, минимум 5 MiB). Согласованность пары
 * partSize/partsCount держится сигнатурой, а не только докблоком.
 */
final readonly class MediaUploadPlanner implements MediaUploadPlannerContract
{
    public function __construct(
        private MediaConfig $mediaConfig,
    ) {}

    public function stagingExpiration(): MediaExpiration
    {
        $expiresAt = new \DateTimeImmutable()->add(
            new \DateInterval(\sprintf('PT%dS', $this->mediaConfig->stagingTtlSeconds)),
        );

        return MediaExpiration::temporaryUntil($expiresAt);
    }

    public function isMultipart(MediaFileSize $size): bool
    {
        return $size->value() >= $this->mediaConfig->multipartThresholdBytes;
    }

    public function partSize(): MediaMultipartPartSize
    {
        return MediaMultipartPartSize::fromInt($this->mediaConfig->multipartPartSizeBytes);
    }

    public function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): MediaMultipartPartsCount
    {
        return MediaMultipartPartsCount::fromInt((int) \ceil($size->value() / $partSize->value()));
    }
}
