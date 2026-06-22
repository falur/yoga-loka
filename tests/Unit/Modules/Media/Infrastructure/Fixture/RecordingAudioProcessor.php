<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure\Fixture;

use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;

/**
 * Подменяет реальный вызов ffmpeg (runEncoding) в аудио-процессоре: либо бросает заданный сбой,
 * либо создаёт выходной файл и возвращает заданный результат. Реальная обработка покрывается
 * интеграционным тестом.
 */
final class RecordingAudioProcessor extends FfmpegMediaAudioProcessor
{
    private \Throwable|null $failure = null;
    private MediaAudioProcessingResult|null $result = null;

    /**
     * @var list<string>
     */
    public array $touchedFiles = [];

    public function failWith(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function succeedWith(MediaAudioProcessingResult $result): void
    {
        $this->result = $result;
    }

    #[\Override]
    protected function runEncoding(
        string $sourceFile,
        MediaAudioConversionSpec $spec,
        string $normalizedFile,
    ): MediaAudioProcessingResult {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        \file_put_contents($normalizedFile, 'normalized');
        $this->touchedFiles = [$sourceFile, $normalizedFile];

        return $this->result ?? throw new \LogicException('Результат обработки не задан.');
    }
}
