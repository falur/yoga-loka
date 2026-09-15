<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Ffmpeg;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use FFMpeg\FFProbe;
use FFMpeg\FFProbe\DataMapping\Stream;

/**
 * Процессор аудио на php-ffmpeg: транскод оригинала в m4a/AAC и извлечение волны амплитуд.
 * Волну php-ffmpeg готовым массивом не отдаёт (только PNG), поэтому берём сырой PCM прямым
 * вызовом ffmpeg (s16le, моно, низкая частота), делим на waveformPeaks интервалов и нормализуем
 * пик каждого в 0..255. Владеет временными файлами и удаляет их в finally при любом исходе.
 */
class FfmpegMediaAudioProcessor extends AbstractFfmpegMediaProcessor implements MediaAudioProcessorContract
{
    private const string NORMALIZED_MIME = 'audio/mp4';
    private const string FAILURE_MESSAGE = 'Не удалось обработать аудио.';
    private const int WAVEFORM_SAMPLE_RATE = 8000;
    // Максимум знакового 16-битного сэмпла (s16: диапазон -32768..32767).
    private const int PCM_FULL_SCALE = 32767;
    // Ширина диапазона беззнакового 16-битного значения (u16): прибавляем для перевода в знаковое.
    private const int PCM_SIGNED_OFFSET = 65536;
    // Граница знака u16: значения >= 32768 — это отрицательные s16, их переводим вычитанием OFFSET.
    private const int PCM_SIGNED_THRESHOLD = 32768;
    private const int WAVEFORM_PEAK_MAX = 255;

    #[\Override]
    public function process(
        MediaStorage $sourceStorage,
        MediaPath $sourcePath,
        MediaAudioConversionSpec $spec,
        MediaStorage $targetStorage,
        MediaPath $normalizedPath,
    ): MediaAudioProcessingResult {
        $sourceFile = $this->mediaFileService->downloadToFile(storage: $sourceStorage, path: $sourcePath);
        $normalizedFile = $this->createTempFile('m4a');

        try {
            $result = $this->encode(sourceFile: $sourceFile, spec: $spec, normalizedFile: $normalizedFile);
            $this->mediaFileService->uploadFromFile(
                storage: $targetStorage,
                path: $normalizedPath,
                localFile: $normalizedFile,
                mimeType: $result->normalizedMimeType,
            );

            return $result;
        } finally {
            $this->deleteTempFile($sourceFile);
            $this->deleteTempFile($normalizedFile);
        }
    }

    private function encode(
        string $sourceFile,
        MediaAudioConversionSpec $spec,
        string $normalizedFile,
    ): MediaAudioProcessingResult {
        try {
            return $this->runEncoding(sourceFile: $sourceFile, spec: $spec, normalizedFile: $normalizedFile);
        } catch (\Throwable $exception) {
            throw self::classifyFailure(exception: $exception, message: self::FAILURE_MESSAGE);
        }
    }

    protected function runEncoding(
        string $sourceFile,
        MediaAudioConversionSpec $spec,
        string $normalizedFile,
    ): MediaAudioProcessingResult {
        $ffmpeg = $this->ffmpeg();
        $probe = $ffmpeg->getFFProbe();

        // «Есть видеопоток» != «не аудио»: обычный mp3/m4a с обложкой альбома несёт поток
        // attached_pic (mjpeg/png), из-за которого php-ffmpeg отдаёт его как Video. Различаем
        // реальное видео и аудио-с-обложкой по факту наличия настоящего аудиопотока и отсутствия
        // настоящей (не-обложечной) видеодорожки; при транскоде обложку выкидываем (-vn в формате).
        self::assertSourceIsAudio(probe: $probe, sourceFile: $sourceFile);

        // php-ffmpeg open() возвращает Video (наследник Audio) для файлов с видеопотоком и Audio
        // для чистого аудио; save() в обоих случаях транскодирует в m4a, а -vn убирает обложку.
        $audio = $ffmpeg->open($sourceFile);
        $format = new NativeAacAudioFormat(sampleRate: $spec->sampleRate);
        $format->setAudioKiloBitrate(\intdiv(num1: $spec->bitrate, num2: 1000));
        $audio->save(format: $format, outputPathfile: $normalizedFile);

        $audioStream = $probe->streams($normalizedFile)->audios()->first();
        if ($audioStream === null) {
            // @codeCoverageIgnoreStart
            // Недостижимо после успешного транскода: в готовом m4a всегда есть аудиопоток.
            throw MediaProcessorFailedException::permanent('В транскодированном аудио нет аудиопотока.');
            // @codeCoverageIgnoreEnd
        }
        $formatInfo = $probe->format($normalizedFile);

        return new MediaAudioProcessingResult(
            normalizedMimeType: MediaMimeType::fromString(self::NORMALIZED_MIME),
            normalizedSize: MediaFileSize::fromInt((int) \filesize($normalizedFile)),
            duration: MediaDuration::fromInt((int) \round(self::probeFloat(data: $formatInfo, key: 'duration') * 1000)),
            bitrate: MediaBitrate::fromInt(self::probeInt(data: $formatInfo, key: 'bit_rate')),
            sampleRate: MediaSampleRate::fromInt(self::probeInt(data: $audioStream, key: 'sample_rate')),
            waveform: $this->extractWaveform(sourceFile: $sourceFile, peaks: $spec->waveformPeaks),
        );
    }

    /**
     * Вход — аудио, только если у него есть настоящий аудиопоток и нет настоящей видеодорожки.
     * Поток attached_pic (обложка альбома) видеодорожкой для этой проверки не считается, поэтому
     * mp3/m4a с обложкой проходит, а реальное видео (есть аудио + движущееся видео) — отклоняется.
     * Видеопотоков у аудиофайла не больше одного (обложка), поэтому хватает первого.
     */
    private static function assertSourceIsAudio(FFProbe $probe, string $sourceFile): void
    {
        $streams = $probe->streams($sourceFile);

        if ($streams->audios()->first() === null) {
            throw MediaProcessorFailedException::permanent('Загруженный файл не является аудио.');
        }

        $videoStream = $streams->videos()->first();
        if ($videoStream !== null && !self::isAttachedPicture($videoStream)) {
            throw MediaProcessorFailedException::permanent('Загруженный файл не является аудио.');
        }
    }

    /**
     * Видеопоток — встроенная обложка, а не настоящее видео, если ffprobe пометил его
     * disposition.attached_pic = 1. На границе с php-ffmpeg disposition приходит вложенной картой
     * флагов со строковыми значениями (он парсит текстовый вывод ffprobe, не JSON), поэтому читаем
     * нужный флаг точечно, сужаем тип через is_numeric и приводим к int, без протаскивания массива
     * во внутренний код.
     */
    private static function isAttachedPicture(Stream $videoStream): bool
    {
        $disposition = $videoStream->get('disposition');
        if (!\is_array($disposition)) {
            return false;
        }

        $attachedPic = $disposition['attached_pic'] ?? 0;

        return \is_numeric($attachedPic) && (int) $attachedPic === 1;
    }

    private function extractWaveform(string $sourceFile, int $peaks): MediaWaveform
    {
        $pcmFile = $this->createTempFile('pcm');

        try {
            $this->runFfmpeg([
                '-i', $sourceFile,
                '-ac', '1',
                '-ar', (string) self::WAVEFORM_SAMPLE_RATE,
                '-f', 's16le',
                '-acodec', 'pcm_s16le',
                '-y', $pcmFile,
            ]);

            return $this->buildWaveform(pcm: (string) \file_get_contents($pcmFile), peaks: $peaks);
        } finally {
            $this->deleteTempFile($pcmFile);
        }
    }

    protected function buildWaveform(string $pcm, int $peaks): MediaWaveform
    {
        /** @var array<int, int>|false $unpacked */
        $unpacked = \unpack(format: 'v*', string: $pcm);
        if ($unpacked === false) {
            // @codeCoverageIgnoreStart
            // Недостижимо: unpack('v*', ...) на любой строке возвращает массив (пустой для пустой).
            throw MediaProcessorFailedException::permanent('Не удалось прочитать PCM-данные аудио.');
            // @codeCoverageIgnoreEnd
        }

        $samples = \array_values($unpacked);
        // Волна строится из того же файла, который выше уже успешно открыт как аудио (open()), поэтому
        // PCM непуст. Полностью пустой буфер (sampleCount === 0) сюда не доходит, а если бы дошёл —
        // плоская тишина из нулей это валидный результат, а не сбой; отдельной ошибки на него не нужно.
        $sampleCount = \count($samples);

        $amplitudes = [];
        for ($peakIndex = 0; $peakIndex < $peaks; $peakIndex++) {
            $start = \intdiv(num1: $peakIndex * $sampleCount, num2: $peaks);
            $end = \intdiv(num1: ($peakIndex + 1) * $sampleCount, num2: $peaks);

            // Короткое аудио (waveformPeaks > число сэмплов) даёт пустые интервалы (start === end).
            // Без этого пустой интервал молча вернул бы 0 (плоская тишина вместо реального пика);
            // берём хотя бы один ближайший сэмпл, ограничив индекс последним доступным.
            if ($end === $start && $sampleCount > 0) {
                $end = \min($start + 1, $sampleCount);
            }

            $maxAmplitude = 0;
            for ($sampleIndex = $start; $sampleIndex < $end; $sampleIndex++) {
                $signed = $samples[$sampleIndex];
                if ($signed >= self::PCM_SIGNED_THRESHOLD) {
                    $signed -= self::PCM_SIGNED_OFFSET;
                }

                $magnitude = \abs($signed);
                if ($magnitude > $maxAmplitude) {
                    $maxAmplitude = $magnitude;
                }
            }

            // Максимум magnitude = 32768 (для -32768): 32768/32767*255 округляется до 255, поэтому
            // значение всегда укладывается в 0..255 без дополнительного ограничения.
            $amplitudes[] = (int) \round($maxAmplitude / self::PCM_FULL_SCALE * self::WAVEFORM_PEAK_MAX);
        }

        return MediaWaveform::fromPeaks($amplitudes);
    }
}
