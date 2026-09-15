<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Ffmpeg;

use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Public\Dto\MediaVideoConversionSpecDto;
use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Video\X264;
use FFMpeg\Media\Video;

/**
 * Процессор видео на php-ffmpeg: транскод оригинала в mp4/H.264+AAC с сохранением пропорций
 * (масштаб «inset», стороны округляются до чётных) и кадр-постер JPEG размером транскода.
 * Владеет временными файлами и удаляет их в finally при любом исходе. Ориентацию ffmpeg
 * применяет по display matrix автоматически при декодировании.
 */
class FfmpegMediaVideoProcessor extends AbstractFfmpegMediaProcessor implements MediaVideoProcessorContract
{
    private const string NORMALIZED_MIME = 'video/mp4';
    private const string POSTER_MIME = 'image/jpeg';
    private const string FAILURE_MESSAGE = 'Не удалось обработать видео.';
    // Тихая стереодорожка для немого входа: гарантирует AAC в выходе (контракт H.264+AAC).
    private const string SILENT_AUDIO_SOURCE = 'anullsrc=channel_layout=stereo:sample_rate=44100';

    #[\Override]
    public function process(
        MediaStorage $sourceStorage,
        MediaPath $sourcePath,
        MediaVideoConversionSpecDto $spec,
        MediaStorage $targetStorage,
        MediaPath $normalizedPath,
        MediaPath $posterPath,
    ): MediaVideoProcessingResult {
        $sourceFile = $this->mediaFileService->downloadToFile(storage: $sourceStorage, path: $sourcePath);
        $normalizedFile = $this->createTempFile('mp4');
        $posterFile = $this->createTempFile('jpg');

        try {
            $result = $this->encode(
                sourceFile: $sourceFile,
                spec: $spec,
                normalizedFile: $normalizedFile,
                posterFile: $posterFile,
            );
            $this->mediaFileService->uploadFromFile(
                storage: $targetStorage,
                path: $normalizedPath,
                localFile: $normalizedFile,
                mimeType: $result->normalizedMimeType,
            );
            $this->mediaFileService->uploadFromFile(
                storage: $targetStorage,
                path: $posterPath,
                localFile: $posterFile,
                mimeType: $result->posterMimeType,
            );

            return $result;
        } finally {
            $this->deleteTempFile($sourceFile);
            $this->deleteTempFile($normalizedFile);
            $this->deleteTempFile($posterFile);
        }
    }

    private function encode(
        string $sourceFile,
        MediaVideoConversionSpecDto $spec,
        string $normalizedFile,
        string $posterFile,
    ): MediaVideoProcessingResult {
        try {
            return $this->runEncoding(
                sourceFile: $sourceFile,
                spec: $spec,
                normalizedFile: $normalizedFile,
                posterFile: $posterFile,
            );
        } catch (\Throwable $exception) {
            throw self::classifyFailure(exception: $exception, message: self::FAILURE_MESSAGE);
        }
    }

    protected function runEncoding(
        string $sourceFile,
        MediaVideoConversionSpecDto $spec,
        string $normalizedFile,
        string $posterFile,
    ): MediaVideoProcessingResult {
        $ffmpeg = $this->ffmpeg();

        $video = $ffmpeg->open($sourceFile);
        if (!$video instanceof Video) {
            throw MediaProcessorFailedException::permanent('Загруженный файл не является видео.');
        }

        $format = new X264();
        $format->setKiloBitrate(\intdiv(num1: $spec->videoBitrate, num2: 1000));
        $format->setAudioKiloBitrate(\intdiv(num1: $spec->audioBitrate, num2: 1000));
        // Контракт выхода — mp4/H.264+AAC (решение плана 3). php-ffmpeg добавляет AAC только когда у
        // входа есть аудиопоток, поэтому немому видео подмешиваем тихую дорожку anullsrc вторым входом
        // (-shortest обрезает её по длине видео), чтобы выход всегда был H.264+AAC.
        if ($ffmpeg->getFFProbe()->streams($sourceFile)->audios()->first() === null) {
            $format->setInitialParameters(['-f', 'lavfi', '-i', self::SILENT_AUDIO_SOURCE]);
            $format->setAdditionalParameters(['-shortest']);
        }
        $video->addFilter(new ResizeFilter(
            dimension: new Dimension(width: $spec->width, height: $spec->height),
            mode: ResizeFilter::RESIZEMODE_INSET,
        ));
        $video->save(format: $format, outputPathfile: $normalizedFile);

        $this->extractPoster(normalizedFile: $normalizedFile, posterFile: $posterFile);

        $probe = $ffmpeg->getFFProbe();
        $videoStream = $probe->streams($normalizedFile)->videos()->first();
        if ($videoStream === null) {
            // @codeCoverageIgnoreStart
            // Недостижимо после успешного транскода: в готовом mp4 всегда есть видеопоток.
            throw MediaProcessorFailedException::permanent('В транскодированном видео нет видеопотока.');
            // @codeCoverageIgnoreEnd
        }
        $formatInfo = $probe->format($normalizedFile);

        return new MediaVideoProcessingResult(
            normalizedMimeType: MediaMimeType::fromString(self::NORMALIZED_MIME),
            normalizedSize: MediaFileSize::fromInt((int) \filesize($normalizedFile)),
            width: MediaPixelDimension::fromInt(self::probeInt(data: $videoStream, key: 'width')),
            height: MediaPixelDimension::fromInt(self::probeInt(data: $videoStream, key: 'height')),
            duration: MediaDuration::fromInt((int) \round(self::probeFloat(data: $formatInfo, key: 'duration') * 1000)),
            bitrate: MediaBitrate::fromInt(self::probeInt(data: $formatInfo, key: 'bit_rate')),
            posterMimeType: MediaMimeType::fromString(self::POSTER_MIME),
            posterSize: MediaFileSize::fromInt((int) \filesize($posterFile)),
        );
    }

    /**
     * Первый кадр транскода как постер JPEG прямым вызовом ffmpeg: читает из нормализованного
     * файла, поэтому постер выходит размером транскода (решение плана), без повторного open().
     */
    private function extractPoster(string $normalizedFile, string $posterFile): void
    {
        $this->runFfmpeg([
            '-i', $normalizedFile,
            '-frames:v', '1',
            '-y', $posterFile,
        ]);
    }
}
