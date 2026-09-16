<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Domain\ValueObject\MediaFileSize;

/**
 * Метаданные объекта в хранилище (результат headObject). null от контракта означает,
 * что объекта нет.
 */
final readonly class MediaObjectHead
{
    public function __construct(
        public MediaFileSize $contentLength,
    ) {}
}
