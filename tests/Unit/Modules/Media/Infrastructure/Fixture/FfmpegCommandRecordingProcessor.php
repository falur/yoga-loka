<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Infrastructure\Fixture;

use App\Modules\Media\Infrastructure\Ffmpeg\AbstractFfmpegMediaProcessor;

/**
 * Открывает защищённый runFfmpeg() общего процессора и подменяет реальный запуск процесса:
 * вместо mustRun() сохраняет собранную команду, чтобы юнит-тест проверил состав аргументов
 * (например, наличие `-threads` при ffmpegThreads > 0) без реального бинаря ffmpeg.
 */
final class FfmpegCommandRecordingProcessor extends AbstractFfmpegMediaProcessor
{
    /**
     * @var list<string>
     */
    public array $command = [];

    /**
     * @param list<string> $arguments
     */
    public function runFfmpegWith(array $arguments): void
    {
        $this->runFfmpeg($arguments);
    }

    /**
     * @param list<string> $command
     */
    #[\Override]
    protected function runFfmpegProcess(array $command): void
    {
        $this->command = $command;
    }
}
