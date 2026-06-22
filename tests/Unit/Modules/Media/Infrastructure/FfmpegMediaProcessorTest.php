<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
use App\Modules\Media\Infrastructure\FileService\NativeAacAudioFormat;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use FFMpeg\FFProbe\DataMapping\Stream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\Unit\Modules\Media\Infrastructure\Fixture\FfmpegCommandRecordingProcessor;
use Tests\Unit\Modules\Media\Infrastructure\Fixture\RecordingAudioProcessor;
use Tests\Unit\Modules\Media\Infrastructure\Fixture\RecordingVideoProcessor;

final class FfmpegMediaProcessorTest extends TestCase
{
    public function testMediaProcessorFailedExceptionExposesTransience(): void
    {
        $previous = new \RuntimeException('сырой вывод ffmpeg');

        $transient = MediaProcessorFailedException::transient(message: 'Не удалось обработать видео.', previous: $previous);
        $permanent = MediaProcessorFailedException::permanent(message: 'Не удалось обработать аудио.', previous: $previous);

        self::assertTrue($transient->isTransient());
        self::assertFalse($permanent->isTransient());
        self::assertSame($previous, $transient->getPrevious());
        self::assertSame('Не удалось обработать видео.', $transient->getMessage());
    }

    public function testNativeAacAudioFormatUsesNativeCodec(): void
    {
        $format = new NativeAacAudioFormat(sampleRate: 44_100);

        self::assertSame('aac', $format->getAudioCodec());
        self::assertSame(['aac'], $format->getAvailableAudioCodecs());
    }

    public function testNativeAacAudioFormatPassesProfileSampleRateToFfmpeg(): void
    {
        $format = new NativeAacAudioFormat(sampleRate: 22_050);

        // -vn выкидывает встроенную обложку (attached_pic) из аудиовыхода; затем -ar задаёт частоту.
        self::assertSame(['-vn', '-ar', '22050'], $format->getExtraParams());
    }

    public function testVideoProcessorClassifiesTimeoutAsTransient(): void
    {
        $sourceFile = $this->trackedSourceFile();
        $processor = new RecordingVideoProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
        $processor->failWith($this->timeout());

        try {
            $this->processVideo($processor);
            self::fail('Ожидалось MediaProcessorFailedException.');
        } catch (MediaProcessorFailedException $exception) {
            self::assertTrue($exception->isTransient());
        }

        self::assertFileDoesNotExist($sourceFile);
    }

    public function testVideoProcessorClassifiesGenericFailureAsPermanent(): void
    {
        $sourceFile = $this->trackedSourceFile();
        $processor = new RecordingVideoProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
        $processor->failWith(new \RuntimeException('битый файл'));

        try {
            $this->processVideo($processor);
            self::fail('Ожидалось MediaProcessorFailedException.');
        } catch (MediaProcessorFailedException $exception) {
            self::assertFalse($exception->isTransient());
        }

        self::assertFileDoesNotExist($sourceFile);
    }

    public function testVideoProcessorUploadsResultAndCleansTempFilesOnSuccess(): void
    {
        $sourceFile = $this->trackedSourceFile();
        $processor = new RecordingVideoProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
        $processor->succeedWith($this->videoResult());

        $result = $this->processVideo($processor);

        self::assertSame('video/mp4', $result->normalizedMimeType->value());
        self::assertSame(1280, $result->width->value());
        self::assertNotSame([], $processor->touchedFiles);
        foreach ($processor->touchedFiles as $touchedFile) {
            self::assertFileDoesNotExist($touchedFile);
        }
    }

    public function testVideoProcessorCleansTempFilesWhenUploadFails(): void
    {
        $sourceFile = $this->trackedSourceFile();
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('downloadToFile')->willReturn($sourceFile);
        $fileService->method('uploadFromFile')->willThrowException(
            MediaFileServiceFailedException::permanent('Ошибка хранилища.'),
        );

        $processor = new RecordingVideoProcessor($fileService, $this->mediaConfig());
        $processor->succeedWith($this->videoResult());

        try {
            $this->processVideo($processor);
            self::fail('Ожидалось MediaFileServiceFailedException.');
        } catch (MediaFileServiceFailedException) {
            // ожидаемо
        }

        self::assertNotSame([], $processor->touchedFiles);
        foreach ($processor->touchedFiles as $touchedFile) {
            self::assertFileDoesNotExist($touchedFile);
        }
    }

    public function testAudioProcessorClassifiesTimeoutAsTransient(): void
    {
        $sourceFile = $this->trackedSourceFile();
        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
        $processor->failWith($this->timeout());

        try {
            $this->processAudio($processor);
            self::fail('Ожидалось MediaProcessorFailedException.');
        } catch (MediaProcessorFailedException $exception) {
            self::assertTrue($exception->isTransient());
        }

        self::assertFileDoesNotExist($sourceFile);
    }

    public function testAudioProcessorClassifiesGenericFailureAsPermanent(): void
    {
        $sourceFile = $this->trackedSourceFile();
        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
        $processor->failWith(new \RuntimeException('битый файл'));

        try {
            $this->processAudio($processor);
            self::fail('Ожидалось MediaProcessorFailedException.');
        } catch (MediaProcessorFailedException $exception) {
            self::assertFalse($exception->isTransient());
        }

        self::assertFileDoesNotExist($sourceFile);
    }

    public function testAudioProcessorUploadsResultAndCleansTempFilesOnSuccess(): void
    {
        $sourceFile = $this->trackedSourceFile();
        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
        $processor->succeedWith($this->audioResult());

        $result = $this->processAudio($processor);

        self::assertSame('audio/mp4', $result->normalizedMimeType->value());
        self::assertSame([0, 128, 255], $result->waveform->peaks());
        self::assertNotSame([], $processor->touchedFiles);
        foreach ($processor->touchedFiles as $touchedFile) {
            self::assertFileDoesNotExist($touchedFile);
        }
    }

    public function testAudioProcessorCleansTempFilesWhenUploadFails(): void
    {
        $sourceFile = $this->trackedSourceFile();
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('downloadToFile')->willReturn($sourceFile);
        $fileService->method('uploadFromFile')->willThrowException(
            MediaFileServiceFailedException::permanent('Ошибка хранилища.'),
        );

        $processor = new RecordingAudioProcessor($fileService, $this->mediaConfig());
        $processor->succeedWith($this->audioResult());

        try {
            $this->processAudio($processor);
            self::fail('Ожидалось MediaFileServiceFailedException.');
        } catch (MediaFileServiceFailedException) {
            // ожидаемо
        }

        self::assertNotSame([], $processor->touchedFiles);
        foreach ($processor->touchedFiles as $touchedFile) {
            self::assertFileDoesNotExist($touchedFile);
        }
    }

    public function testRunFfmpegAddsThreadsLimitWhenConfigured(): void
    {
        $processor = new FfmpegCommandRecordingProcessor(
            $this->createStub(MediaFileServiceContract::class),
            $this->mediaConfig(ffmpegThreads: 2),
        );

        $processor->runFfmpegWith(['-i', 'in.wav', '-y', 'out.pcm']);

        self::assertSame(
            ['/usr/bin/ffmpeg', '-threads', '2', '-i', 'in.wav', '-y', 'out.pcm'],
            $processor->command,
        );
    }

    public function testRunFfmpegOmitsThreadsLimitWhenZero(): void
    {
        $processor = new FfmpegCommandRecordingProcessor(
            $this->createStub(MediaFileServiceContract::class),
            $this->mediaConfig(),
        );

        $processor->runFfmpegWith(['-i', 'in.wav', '-y', 'out.pcm']);

        self::assertSame(
            ['/usr/bin/ffmpeg', '-i', 'in.wav', '-y', 'out.pcm'],
            $processor->command,
        );
    }

    public function testIsAttachedPictureTreatsNonArrayDispositionAsNotCover(): void
    {
        // На границе с php-ffmpeg get('disposition') возвращает mixed: для нестандартного вывода
        // ffprobe это может быть не карта флагов, а скаляр. Реальный бинарь всегда отдаёт массив,
        // поэтому guard !is_array недостижим через интеграцию — покрываем его прямым вызовом.
        $videoStream = new Stream(['codec_type' => 'video', 'disposition' => 'не-массив']);

        $isAttachedPicture = (new \ReflectionMethod(FfmpegMediaAudioProcessor::class, 'isAttachedPicture'))
            ->invoke(null, $videoStream);

        self::assertFalse($isAttachedPicture);
    }

    private function processVideo(RecordingVideoProcessor $processor): MediaVideoProcessingResult
    {
        $storageKey = MediaStorageKey::generate();

        return $processor->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4'),
            spec: new MediaVideoConversionSpec(
                type: MediaVideoConversionType::NormalizedMp4H264,
                width: 1280,
                height: 720,
                videoBitrate: 1_000_000,
                audioBitrate: 128_000,
            ),
            targetStorage: MediaStorage::Public,
            normalizedPath: MediaPath::videoConversion(
                storageKey: $storageKey,
                type: MediaVideoConversionType::NormalizedMp4H264,
                extension: 'mp4',
            ),
            posterPath: MediaPath::imageConversion(
                storageKey: $storageKey,
                type: MediaImageConversionType::Poster,
                extension: 'jpg',
            ),
        );
    }

    private function processAudio(RecordingAudioProcessor $processor): MediaAudioProcessingResult
    {
        $storageKey = MediaStorageKey::generate();

        return $processor->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: MediaPath::originalUpload(storageKey: $storageKey, extension: 'wav'),
            spec: new MediaAudioConversionSpec(
                type: MediaAudioConversionType::NormalizedAacM4a,
                bitrate: 128_000,
                sampleRate: 44_100,
                waveformPeaks: 64,
            ),
            targetStorage: MediaStorage::Public,
            normalizedPath: MediaPath::audioConversion(
                storageKey: $storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
        );
    }

    private function videoResult(): MediaVideoProcessingResult
    {
        return new MediaVideoProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('video/mp4'),
            normalizedSize: MediaFileSize::fromInt(2048),
            width: MediaPixelDimension::fromInt(1280),
            height: MediaPixelDimension::fromInt(720),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(1_000_000),
            posterMimeType: MediaMimeType::fromString('image/jpeg'),
            posterSize: MediaFileSize::fromInt(512),
        );
    }

    private function audioResult(): MediaAudioProcessingResult
    {
        return new MediaAudioProcessingResult(
            normalizedMimeType: MediaMimeType::fromString('audio/mp4'),
            normalizedSize: MediaFileSize::fromInt(1024),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 128, 255]),
        );
    }

    private function fileServiceReturning(string $sourceFile): MediaFileServiceContract
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('downloadToFile')->willReturn($sourceFile);

        return $fileService;
    }

    private function trackedSourceFile(): string
    {
        $sourceFile = (string) \tempnam(\sys_get_temp_dir(), 'ffmpeg_src_');
        \file_put_contents($sourceFile, 'source');

        return $sourceFile;
    }

    private function timeout(): ProcessTimedOutException
    {
        return new ProcessTimedOutException(new Process(['true']), ProcessTimedOutException::TYPE_GENERAL);
    }

    private function mediaConfig(int $ffmpegThreads = 0): MediaConfig
    {
        return new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 16_777_216,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: $ffmpegThreads,
        );
    }
}
