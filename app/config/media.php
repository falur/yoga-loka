<?php

declare(strict_types=1);

/**
 * Инфраструктурные дефолты модуля Media.
 *
 * Только технические параметры пайплайна загрузки: staging-TTL для MediaExpiration при
 * создании, порог и размер части multipart, драйвер обработки изображений. Бизнес-ограничения
 * загрузки и срок presigned-ссылок сюда не зашиваются — их задаёт потребитель (срок presigned —
 * через MediaUploadSpec для загрузки и GetMediaUrlQuery для скачивания).
 */
return [
    // Срок жизни оригинала в staging-бакете до подтверждения (MediaExpiration при create), секунды.
    'stagingTtlSeconds' => \max(1, (int) \env('MEDIA_STAGING_TTL_SECONDS', 86_400)),

    // Порог: файл размером >= порога загружается через multipart, иначе одиночным PUT.
    'multipartThresholdBytes' => \max(5_242_880, (int) \env('MEDIA_MULTIPART_THRESHOLD_BYTES', 16_777_216)),

    // Размер одной части multipart, байты. S3 требует >= 5 MiB на часть (кроме последней).
    'multipartPartSizeBytes' => \max(5_242_880, (int) \env('MEDIA_MULTIPART_PART_SIZE_BYTES', 8_388_608)),

    // Драйвер обработки изображений Intervention Image: "imagick" (основной) или "gd" (фолбэк).
    'imageProcessingDriver' => (string) \env('MEDIA_IMAGE_PROCESSING_DRIVER', 'imagick'),
];
