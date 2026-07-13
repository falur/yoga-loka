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
        public int $presignedTtlSeconds,
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

        // Верхняя граница срока presigned-ссылки скачивания: значение больше 7 суток (604800) конфиг
        // принял бы (\max(1, ...) обрезает только снизу), но хранилище (S3 SigV4) не подпишет ссылку на
        // срок больше 7 суток — на первом же построении ссылки для приватного медиа был бы отказ.
        // Поэтому отвергаем сразу при старте. Это единственное место, где живёт лимит подписи хранилища:
        // доменный MediaPresignedTtl проверяет только положительность и предела не знает.
        if ($presignedTtlSeconds > 604_800) {
            throw new InvalidConfigValueException(
                path: 'media.presignedTtlSeconds',
                expected: '<= 604800',
                actual: (string) $presignedTtlSeconds,
            );
        }
    }
}
