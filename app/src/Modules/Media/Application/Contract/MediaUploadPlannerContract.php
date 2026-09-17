<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;

/**
 * Контракт решений пайплайна загрузки, зависящих от конфига Media: срок staging-хранения нового медиа,
 * нужен ли multipart для данного размера, размер и число частей multipart. Application-сценарий
 * (RequestMediaUploadHandler) получает эти решения через контракт и не знает про *Config.
 *
 * Реализация — App\Modules\Media\Infrastructure\Storage\MediaUploadPlanner (читает MediaConfig
 * напрямую через конструктор, как MediaUrlService).
 */
interface MediaUploadPlannerContract
{
    public function stagingExpiration(): MediaExpiration;

    public function isMultipart(MediaFileSize $size): bool;

    /**
     * Размер одной части multipart-загрузки. Этот же объект вызывающий передаёт в partsCount(), поэтому
     * число частей всегда считается делением на тот размер части, который записывается в
     * MediaMultipartUpload.
     */
    public function partSize(): MediaMultipartPartSize;

    /**
     * Число частей для размера size при заданном размере части partSize. Согласованность пары
     * (partsCount, partSize) держится сигнатурой, а не докблоком: partSize приходит готовым объектом
     * (от partSize()), поэтому реализация не может разделить на другое значение и собрать
     * MediaMultipartUpload с несогласованной парой.
     */
    public function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): MediaMultipartPartsCount;
}
