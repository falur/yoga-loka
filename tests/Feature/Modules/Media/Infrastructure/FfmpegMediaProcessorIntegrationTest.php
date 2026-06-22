<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Infrastructure;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaVideoProcessor;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Интеграционный тест ffmpeg-процессоров на реальном бинаре (Docker) и MinIO. Фикстуры медиа
 * генерируются прямо в тесте через ffmpeg lavfi (testsrc/sine), бинарных файлов в репозитории нет.
 */
final class FfmpegMediaProcessorIntegrationTest extends TestCase
{
    /**
     * @var list<array{storage: MediaStorage, path: MediaPath}>
     */
    private array $createdObjects = [];

    public function testVideoProcessorTranscodesToMp4WithPoster(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4');
        $this->putSource($sourcePath, $this->generateVideoBytes(), 'video/mp4');

        $normalizedPath = MediaPath::videoConversion(
            storageKey: $storageKey,
            type: MediaVideoConversionType::NormalizedMp4H264,
            extension: 'mp4',
        );
        $posterPath = MediaPath::imageConversion(
            storageKey: $storageKey,
            type: MediaImageConversionType::Poster,
            extension: 'jpg',
        );

        $result = $this->videoProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: $sourcePath,
            spec: $this->videoSpec(),
            targetStorage: MediaStorage::Public,
            normalizedPath: $normalizedPath,
            posterPath: $posterPath,
        );
        $this->track(MediaStorage::Public, $normalizedPath);
        $this->track(MediaStorage::Public, $posterPath);

        self::assertSame('video/mp4', $result->normalizedMimeType->value());
        self::assertSame('image/jpeg', $result->posterMimeType->value());
        // Вход 320x240 (4:3), профиль 640x480 (4:3): inset-масштаб сохраняет пропорции, не выходит
        // за рамку профиля и округляет стороны до чётных (H.264). Без этих проверок «width/height > 0»
        // не фиксировал бы контракт транскода.
        self::assertGreaterThan(0, $result->width->value());
        self::assertGreaterThan(0, $result->height->value());
        self::assertLessThanOrEqual(640, $result->width->value());
        self::assertLessThanOrEqual(480, $result->height->value());
        self::assertSame(0, $result->width->value() % 2);
        self::assertSame(0, $result->height->value() % 2);
        self::assertSame(
            \round(320 / 240, 2),
            \round($result->width->value() / $result->height->value(), 2),
        );
        self::assertGreaterThanOrEqual(1, $result->duration->value());
        self::assertGreaterThanOrEqual(1, $result->bitrate->value());
        self::assertGreaterThan(0, $result->normalizedSize->value());
        self::assertGreaterThan(0, $result->posterSize->value());
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $posterPath));
        // План требует именно H.264 (видео) + AAC (аудио): без проверки кодеков смена формата при
        // тех же MIME/размерах прошла бы незамеченной. Читаем codec_name потоков выхода через ffprobe.
        $codecs = $this->probeStreamCodecs(MediaStorage::Public, $normalizedPath);
        self::assertContains('h264', $codecs);
        self::assertContains('aac', $codecs);
    }

    public function testVideoProcessorAddsSilentAacTrackForSilentInput(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4');
        // Немое видео без аудиодорожки: контракт H.264+AAC требует подмешать тихую AAC-дорожку,
        // иначе php-ffmpeg не добавил бы аудио и выход остался бы без AAC.
        $this->putSource($sourcePath, $this->generateSilentVideoBytes(), 'video/mp4');

        $normalizedPath = MediaPath::videoConversion(
            storageKey: $storageKey,
            type: MediaVideoConversionType::NormalizedMp4H264,
            extension: 'mp4',
        );
        $posterPath = MediaPath::imageConversion(
            storageKey: $storageKey,
            type: MediaImageConversionType::Poster,
            extension: 'jpg',
        );

        $result = $this->videoProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: $sourcePath,
            spec: $this->videoSpec(),
            targetStorage: MediaStorage::Public,
            normalizedPath: $normalizedPath,
            posterPath: $posterPath,
        );
        $this->track(MediaStorage::Public, $normalizedPath);
        $this->track(MediaStorage::Public, $posterPath);

        self::assertSame('video/mp4', $result->normalizedMimeType->value());
        $codecs = $this->probeStreamCodecs(MediaStorage::Public, $normalizedPath);
        self::assertContains('h264', $codecs);
        self::assertContains('aac', $codecs);
    }

    public function testAudioProcessorTranscodesAudioWithEmbeddedCover(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'm4a');
        // Обычный аудиофайл с обложкой альбома: обложка — поток attached_pic (mjpeg), из-за которого
        // php-ffmpeg отдаёт файл как Video. Прежняя проверка instanceof Video терминально отклоняла
        // такой штатный вход; теперь он обрабатывается в m4a + волну, а обложка выкидывается (-vn).
        $this->putSource($sourcePath, $this->generateAudioWithCoverBytes(), 'audio/mp4');

        $normalizedPath = MediaPath::audioConversion(
            storageKey: $storageKey,
            type: MediaAudioConversionType::NormalizedAacM4a,
            extension: 'm4a',
        );

        $result = $this->audioProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: $sourcePath,
            spec: $this->audioSpec(),
            targetStorage: MediaStorage::Public,
            normalizedPath: $normalizedPath,
        );
        $this->track(MediaStorage::Public, $normalizedPath);

        self::assertSame('audio/mp4', $result->normalizedMimeType->value());
        self::assertCount(64, $result->waveform->peaks());
        self::assertGreaterThanOrEqual(1, $result->duration->value());
        // Выход — чистое аудио: обложка (видеопоток) в нормализованный m4a не попала.
        $codecs = $this->probeStreamCodecs(MediaStorage::Public, $normalizedPath);
        self::assertContains('aac', $codecs);
        self::assertNotContains('mjpeg', $codecs);
    }

    public function testAudioProcessorTranscodesToM4aWithWaveform(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'wav');
        $this->putSource($sourcePath, $this->generateAudioBytes(), 'audio/wav');

        $normalizedPath = MediaPath::audioConversion(
            storageKey: $storageKey,
            type: MediaAudioConversionType::NormalizedAacM4a,
            extension: 'm4a',
        );

        $result = $this->audioProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: $sourcePath,
            spec: $this->audioSpec(),
            targetStorage: MediaStorage::Public,
            normalizedPath: $normalizedPath,
        );
        $this->track(MediaStorage::Public, $normalizedPath);

        $peaks = $result->waveform->peaks();

        self::assertSame('audio/mp4', $result->normalizedMimeType->value());
        self::assertCount(64, $peaks);
        self::assertGreaterThan(0, \max($peaks));
        self::assertLessThanOrEqual(255, \max($peaks));
        self::assertGreaterThanOrEqual(0, \min($peaks));
        self::assertGreaterThanOrEqual(1, $result->duration->value());
        self::assertGreaterThanOrEqual(8000, $result->sampleRate->value());
        self::assertGreaterThanOrEqual(1, $result->bitrate->value());
        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
    }

    public function testAudioProcessorAppliesProfileSampleRate(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'wav');
        // Вход 48000 Гц при профиле 44100 Гц: проверяем, что выход получает именно профильную частоту,
        // а не унаследованную от исходника (без этого spec.sampleRate влиял бы только на валидацию).
        $this->putSource($sourcePath, $this->generateAudioBytes(sampleRate: 48_000), 'audio/wav');

        $normalizedPath = MediaPath::audioConversion(
            storageKey: $storageKey,
            type: MediaAudioConversionType::NormalizedAacM4a,
            extension: 'm4a',
        );

        $result = $this->audioProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: $sourcePath,
            spec: $this->audioSpec(sampleRate: 44_100),
            targetStorage: MediaStorage::Public,
            normalizedPath: $normalizedPath,
        );
        $this->track(MediaStorage::Public, $normalizedPath);

        self::assertSame(44_100, $result->sampleRate->value());
    }

    public function testVideoProcessorRejectsAudioSource(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'wav');
        $this->putSource($sourcePath, $this->generateAudioBytes(), 'audio/wav');

        $this->expectException(MediaProcessorFailedException::class);

        $this->videoProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: $sourcePath,
            spec: $this->videoSpec(),
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

    public function testAudioProcessorRejectsVideoSource(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4');
        $this->putSource($sourcePath, $this->generateVideoBytes(), 'video/mp4');

        $this->expectException(MediaProcessorFailedException::class);

        $this->audioProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: $sourcePath,
            spec: $this->audioSpec(),
            targetStorage: MediaStorage::Public,
            normalizedPath: MediaPath::audioConversion(
                storageKey: $storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
        );
    }

    public function testAudioProcessorRejectsSourceWithoutAudioStream(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4');
        // Немое видео без аудиодорожки: у файла нет настоящего аудиопотока, поэтому аудио-процессор
        // обязан терминально отклонить его «Загруженный файл не является аудио» ещё на проверке входа,
        // а не упасть позже на транскоде. Покрывает ветку «нет аудиопотока» в assertSourceIsAudio.
        $this->putSource($sourcePath, $this->generateSilentVideoBytes(), 'video/mp4');

        $this->expectException(MediaProcessorFailedException::class);

        $this->audioProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: $sourcePath,
            spec: $this->audioSpec(),
            targetStorage: MediaStorage::Public,
            normalizedPath: MediaPath::audioConversion(
                storageKey: $storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
        );
    }

    public function testVideoProcessorClassifiesBrokenInputAsPermanentFailure(): void
    {
        $storageKey = MediaStorageKey::generate();
        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4');
        $this->putSource($sourcePath, \random_bytes(2048), 'video/mp4');

        try {
            $this->videoProcessor()->process(
                sourceStorage: MediaStorage::Upload,
                sourcePath: $sourcePath,
                spec: $this->videoSpec(),
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
            self::fail('Ожидалось MediaProcessorFailedException.');
        } catch (MediaProcessorFailedException $exception) {
            self::assertFalse($exception->isTransient());
        }
    }

    public function testVideoProcessorFailsWhenSourceMissing(): void
    {
        $storageKey = MediaStorageKey::generate();

        $this->expectException(MediaFileServiceFailedException::class);

        $this->videoProcessor()->process(
            sourceStorage: MediaStorage::Upload,
            sourcePath: MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4'),
            spec: $this->videoSpec(),
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

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->createdObjects as $createdObject) {
            $this->fileService()->deleteObject($createdObject['storage'], $createdObject['path']);
        }

        $this->createdObjects = [];

        parent::tearDown();
    }

    private function videoSpec(): MediaVideoConversionSpec
    {
        return new MediaVideoConversionSpec(
            type: MediaVideoConversionType::NormalizedMp4H264,
            width: 640,
            height: 480,
            videoBitrate: 1_000_000,
            audioBitrate: 128_000,
        );
    }

    private function audioSpec(int $sampleRate = 44_100): MediaAudioConversionSpec
    {
        return new MediaAudioConversionSpec(
            type: MediaAudioConversionType::NormalizedAacM4a,
            bitrate: 128_000,
            sampleRate: $sampleRate,
            waveformPeaks: 64,
        );
    }

    private function putSource(MediaPath $path, string $bytes, string $mime): void
    {
        $this->fileService()->putObject(
            storage: MediaStorage::Upload,
            path: $path,
            contents: $bytes,
            mimeType: MediaMimeType::fromString($mime),
        );
        $this->track(MediaStorage::Upload, $path);
    }

    private function generateVideoBytes(): string
    {
        return $this->generateFixture('mp4', [
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=15',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-shortest', '-pix_fmt', 'yuv420p',
        ]);
    }

    private function generateAudioBytes(int $sampleRate = 44_100): string
    {
        return $this->generateFixture('wav', [
            '-f', 'lavfi', '-i', \sprintf('sine=frequency=440:duration=1:sample_rate=%d', $sampleRate),
        ]);
    }

    private function generateSilentVideoBytes(): string
    {
        return $this->generateFixture('mp4', [
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=15',
            '-pix_fmt', 'yuv420p',
        ]);
    }

    private function generateAudioWithCoverBytes(): string
    {
        // Аудиодорожка (sine) + одиночный кадр testsrc как встроенная обложка (attached_pic, mjpeg).
        return $this->generateFixture('m4a', [
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=1',
            '-frames:v', '1',
            '-map', '0:a', '-map', '1:v',
            '-c:a', 'aac', '-c:v', 'mjpeg', '-disposition:v', 'attached_pic',
        ]);
    }

    /**
     * @param list<string> $inputArgs
     */
    private function generateFixture(string $extension, array $inputArgs): string
    {
        $path = \sprintf('%s/ffmpeg_fixture_%s.%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(8)), $extension);

        $process = new Process([$this->mediaConfig()->ffmpegBinaryPath, ...$inputArgs, '-y', $path]);
        $process->mustRun();

        $bytes = (string) \file_get_contents($path);
        \unlink($path);

        return $bytes;
    }

    /**
     * @return list<string>
     */
    private function probeStreamCodecs(MediaStorage $storage, MediaPath $path): array
    {
        $localFile = $this->fileService()->downloadToFile(storage: $storage, path: $path);

        try {
            $process = new Process([
                $this->mediaConfig()->ffprobeBinaryPath,
                '-v', 'error',
                '-show_entries', 'stream=codec_name',
                '-of', 'csv=p=0',
                $localFile,
            ]);
            $process->mustRun();

            return \array_values(\array_filter(
                \explode("\n", \trim($process->getOutput())),
                static fn(string $codec): bool => $codec !== '',
            ));
        } finally {
            \unlink($localFile);
        }
    }

    private function videoProcessor(): FfmpegMediaVideoProcessor
    {
        return new FfmpegMediaVideoProcessor(
            mediaFileService: $this->fileService(),
            mediaConfig: $this->mediaConfig(),
        );
    }

    private function audioProcessor(): FfmpegMediaAudioProcessor
    {
        return new FfmpegMediaAudioProcessor(
            mediaFileService: $this->fileService(),
            mediaConfig: $this->mediaConfig(),
        );
    }

    private function fileService(): MediaFileServiceContract
    {
        return $this->getContainer()->get(MediaFileServiceContract::class);
    }

    private function mediaConfig(): MediaConfig
    {
        return $this->getContainer()->get(MediaConfig::class);
    }

    private function track(MediaStorage $storage, MediaPath $path): void
    {
        $this->createdObjects[] = ['storage' => $storage, 'path' => $path];
    }
}
