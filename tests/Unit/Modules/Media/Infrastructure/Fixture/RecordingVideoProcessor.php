<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure\Fixture;

use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
use App\Modules\Media\Infrastructure\Ffmpeg\FfmpegMediaVideoProcessor;

/**
 * Подменяет реальный вызов ffmpeg (runEncoding) в видео-процессоре: либо бросает заданный сбой
 * (для проверки классификации), либо создаёт выходные файлы и возвращает заданный результат
 * (для проверки загрузки и очистки временных файлов в finally). Сам ffmpeg тут не вызывается —
 * реальная обработка покрывается интеграционным тестом.
 */
final class RecordingVideoProcessor extends FfmpegMediaVideoProcessor
{
    private \Throwable|null $failure = null;
    private MediaVideoProcessingResult|null $result = null;

    /**
     * @var list<string>
     */
    public array $touchedFiles = [];

    public function failWith(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function succeedWith(MediaVideoProcessingResult $result): void
    {
        $this->result = $result;
    }

    #[\Override]
    protected function runEncoding(
        string $sourceFile,
        MediaVideoConversionSpec $spec,
        string $normalizedFile,
        string $posterFile,
    ): MediaVideoProcessingResult {
        if ($this->failure !== null) {
            // До создания выходных файлов: finally удалит только скачанный оригинал, а выходов нет.
            throw $this->failure;
        }

        \file_put_contents($normalizedFile, 'normalized');
        \file_put_contents($posterFile, 'poster');
        $this->touchedFiles = [$sourceFile, $normalizedFile, $posterFile];

        return $this->result ?? throw new \LogicException('Результат обработки не задан.');
    }
}
