<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\Ffmpeg\FfmpegMediaAudioProcessor;

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
