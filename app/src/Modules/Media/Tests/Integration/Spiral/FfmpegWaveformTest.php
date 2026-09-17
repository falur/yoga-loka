<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Infrastructure\Spiral\Configuration\MediaConfig;
use PHPUnit\Framework\TestCase;

/**
 * Быстрый юнит-тест арифметики волны (buildWaveform) на детерминированных PCM-буферах, без бинаря
 * ffmpeg: нормализация знаковых сэмплов в 0..255, граничные значения и короткий буфер
 * (waveformPeaks > число сэмплов), который раньше молча давал плоские нули.
 */
final class FfmpegWaveformTest extends TestCase
{
    public function testBuildWaveformNormalizesSamplesPerInterval(): void
    {
        // s16le, по одному сэмплу на пик: 0 -> 0, 16384 -> 128, 32767 -> 255, -32768 -> 255.
        $pcm = (string) \pack('v*', 0, 16384, 32767, 32768);

        $waveform = $this->processor()->buildWaveformFrom(pcm: $pcm, peaks: 4);

        self::assertSame([0, 128, 255, 255], $waveform->peaks());
    }

    public function testBuildWaveformFillsEmptyIntervalsForShortBuffer(): void
    {
        // Сэмплов меньше, чем пиков: пустые интервалы берут ближайший реальный сэмпл, а не 0.
        // Тихий 0 и громкий 32767 при 4 пиках -> [0, 0, 255, 255] (без правки было бы [0, 0, 0, 255]).
        $pcm = (string) \pack('v*', 0, 32767);

        $waveform = $this->processor()->buildWaveformFrom(pcm: $pcm, peaks: 4);

        self::assertSame([0, 0, 255, 255], $waveform->peaks());
    }

    public function testBuildWaveformTakesIntervalMaximum(): void
    {
        // Несколько сэмплов на пик: берётся максимум модуля внутри интервала.
        $pcm = (string) \pack('v*', 0, 16384, 0, 32767);

        $waveform = $this->processor()->buildWaveformFrom(pcm: $pcm, peaks: 2);

        self::assertSame([128, 255], $waveform->peaks());
    }

    private function processor(): WaveformExposingAudioProcessor
    {
        return new WaveformExposingAudioProcessor(
            $this->createStub(MediaFileServiceContract::class),
            $this->mediaConfig(),
        );
    }

    private function mediaConfig(): MediaConfig
    {
        return new MediaConfig(
            stagingTtlSeconds: 86_400,
            multipartThresholdBytes: 16_777_216,
            multipartPartSizeBytes: 8_388_608,
            imageProcessingDriver: 'imagick',
            ffmpegBinaryPath: '/usr/bin/ffmpeg',
            ffprobeBinaryPath: '/usr/bin/ffprobe',
            ffmpegTimeoutSeconds: 1800,
            ffmpegThreads: 0,
            presignedTtlSeconds: 3600,
        );
    }
}
