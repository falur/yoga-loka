<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;

/**
 * Инфраструктурные дефолты модуля Media.
 *
 * Технические параметры пайплайна загрузки (staging-TTL для MediaExpiration при создании, порог и
 * размер части multipart, драйвер обработки изображений) и срок presigned-ссылки скачивания по
 * умолчанию. Срок presigned-ссылок загрузки задаёт потребитель через MediaUploadSpec; срок скачивания
 * по умолчанию берётся отсюда, но вызывающий может переопределить его в FindMediaUrlsQuery
 * (принимает presignedTtlSeconds).
 */
return [
    // Срок жизни оригинала в staging-бакете до подтверждения (MediaExpiration при create), секунды.
    'stagingTtlSeconds' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'MEDIA_STAGING_TTL_SECONDS', default: 86_400),
        default: 86_400,
    )),

    // Срок presigned-ссылки скачивания по умолчанию, секунды. Нижнюю границу (>=1) держит
    // MediaPresignedTtl; верхнюю (<=604800, лимит подписи S3 SigV4) проверяет MediaConfig при старте,
    // чтобы неверная настройка падала на запуске, а не на первом построении ссылки для приватного медиа.
    'presignedTtlSeconds' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'MEDIA_PRESIGNED_TTL_SECONDS', default: 3600),
        default: 3600,
    )),

    // Порог: файл размером >= порога загружается через multipart, иначе одиночным PUT.
    'multipartThresholdBytes' => \max(5_242_880, ConfigArrayFile::int(
        value: \env(key: 'MEDIA_MULTIPART_THRESHOLD_BYTES', default: 16_777_216),
        default: 16_777_216,
    )),

    // Размер одной части multipart, байты. S3 требует >= 5 MiB на часть (кроме последней).
    'multipartPartSizeBytes' => \max(5_242_880, ConfigArrayFile::int(
        value: \env(key: 'MEDIA_MULTIPART_PART_SIZE_BYTES', default: 8_388_608),
        default: 8_388_608,
    )),

    // Драйвер обработки изображений Intervention Image: "imagick" (основной) или "gd" (фолбэк).
    'imageProcessingDriver' => ConfigArrayFile::string(
        value: \env(key: 'MEDIA_IMAGE_PROCESSING_DRIVER', default: 'imagick'),
        default: 'imagick',
    ),

    // Путь к бинарю ffmpeg для транскодирования видео и аудио.
    'ffmpegBinaryPath' => ConfigArrayFile::string(
        value: \env(key: 'MEDIA_FFMPEG_BINARY', default: '/usr/bin/ffmpeg'),
        default: '/usr/bin/ffmpeg',
    ),

    // Путь к бинарю ffprobe для чтения метаданных медиа.
    'ffprobeBinaryPath' => ConfigArrayFile::string(
        value: \env(key: 'MEDIA_FFPROBE_BINARY', default: '/usr/bin/ffprobe'),
        default: '/usr/bin/ffprobe',
    ),

    // Таймаут одного вызова ffmpeg (транскод/probe), секунды.
    'ffmpegTimeoutSeconds' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'MEDIA_FFMPEG_TIMEOUT_SECONDS', default: 1800),
        default: 1800,
    )),

    // Число потоков ffmpeg: 0 = выбирает сам ffmpeg по числу ядер.
    'ffmpegThreads' => \max(0, ConfigArrayFile::int(
        value: \env(key: 'MEDIA_FFMPEG_THREADS', default: 0),
        default: 0,
    )),
];
