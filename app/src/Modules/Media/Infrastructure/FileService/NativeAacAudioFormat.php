<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use FFMpeg\Format\Audio\DefaultAudio;

/**
 * AAC-формат на нативном кодере ffmpeg «aac» вместо libfdk_aac из FFMpeg\Format\Audio\Aac:
 * libfdk_aac не входит в сборку ffmpeg Ubuntu (несвободный), а нативный «aac» доступен всегда.
 * Целевую частоту дискретизации профиля передаём ffmpeg как `-ar <rate>` через getExtraParams():
 * у DefaultAudio нет setAudioSampleRate, а php-ffmpeg других хуков для частоты не даёт.
 * `-vn` выкидывает любую видеодорожку: у обычных mp3/m4a с обложкой альбома это поток
 * attached_pic (mjpeg/png), который не должен попасть в чистый аудиовыход; для аудио без обложки
 * `-vn` — безопасный no-op.
 */
final class NativeAacAudioFormat extends DefaultAudio
{
    public function __construct(
        private readonly int $sampleRate,
    ) {
        $this->audioCodec = 'aac';
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getExtraParams(): array
    {
        return ['-vn', '-ar', (string) $this->sampleRate];
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getAvailableAudioCodecs(): array
    {
        return ['aac'];
    }
}
