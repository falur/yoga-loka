<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Media;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;

final readonly class MediaConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'media';
    }

    public function __construct(
        public int $stagingTtlSeconds,
        public int $multipartThresholdBytes,
        public int $multipartPartSizeBytes,
        public string $imageProcessingDriver,
        public string $ffmpegBinaryPath,
        public string $ffprobeBinaryPath,
        public int $ffmpegTimeoutSeconds,
        public int $ffmpegThreads,
    ) {
        // Порог multipart должен быть не меньше размера части: иначе файл чуть больше порога,
        // но меньше одной части ушёл бы в multipart с единственной частью — это бессмысленно
        // относительно одиночного PUT и потенциально нарушает лимит S3 ≥ 5 MiB на часть.
        if ($multipartThresholdBytes < $multipartPartSizeBytes) {
            throw new InvalidConfigValueException(
                path: 'media.multipartThresholdBytes',
                expected: \sprintf('>= multipartPartSizeBytes (%d)', $multipartPartSizeBytes),
                actual: (string) $multipartThresholdBytes,
            );
        }
    }
}
