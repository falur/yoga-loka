<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure\Fixture;

use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;

/**
 * Открывает защищённый buildWaveform() аудио-процессора для быстрого юнит-теста арифметики PCM
 * без реального бинаря ffmpeg (граничные сэмплы, короткий буфер, неравномерное деление).
 */
final class WaveformExposingAudioProcessor extends FfmpegMediaAudioProcessor
{
    public function buildWaveformFrom(string $pcm, int $peaks): MediaWaveform
    {
        return $this->buildWaveform(pcm: $pcm, peaks: $peaks);
    }
}
