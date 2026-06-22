<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe\DataMapping\AbstractData;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Общая инфраструктура ffmpeg-процессоров видео и аудио: сборка клиента php-ffmpeg из MediaConfig,
 * управление временными файлами и классификация сбоев ffmpeg во временную/постоянную ошибку.
 *
 * Класс не final намеренно: конкретные процессоры выносят сам вызов ffmpeg в защищённый метод
 * runEncoding(), который тест подменяет в наследнике, чтобы покрыть ветки обработки ошибок без
 * реального таймаута бинаря (реальный ffmpeg проверяется интеграционными тестами).
 */
abstract class AbstractFfmpegMediaProcessor
{
    public function __construct(
        protected readonly MediaFileServiceContract $mediaFileService,
        protected readonly MediaConfig $mediaConfig,
    ) {}

    protected function ffmpeg(): FFMpeg
    {
        return FFMpeg::create([
            'ffmpeg.binaries' => $this->mediaConfig->ffmpegBinaryPath,
            'ffprobe.binaries' => $this->mediaConfig->ffprobeBinaryPath,
            'ffmpeg.threads' => $this->mediaConfig->ffmpegThreads,
            'timeout' => $this->mediaConfig->ffmpegTimeoutSeconds,
        ]);
    }

    /**
     * Уникальный путь во временном каталоге с нужным расширением (ffmpeg определяет контейнер по
     * расширению и сам создаёт файл с флагом -y). Без предварительного создания — чтобы не было
     * непокрываемой ветки на ошибку tempnam.
     */
    protected function createTempFile(string $extension): string
    {
        return \sprintf('%s/media_ffmpeg_%s.%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(12)), $extension);
    }

    protected function deleteTempFile(string $path): void
    {
        if (\is_file($path)) {
            \unlink($path);
        }
    }

    /**
     * Прямой запуск внешнего ffmpeg для вспомогательных операций (волна, постер), которые
     * php-ffmpeg готовым API не покрывает. Единая точка распространяет лимит потоков
     * `ffmpegThreads` на эти вызовы (php-ffmpeg учитывает его сам, а прямой Process — нет): при
     * ffmpegThreads > 0 добавляет `-threads <n>` сразу после бинаря.
     *
     * @param list<string> $arguments аргументы ffmpeg после бинаря (например, -i, -y, путь выхода)
     */
    protected function runFfmpeg(array $arguments): void
    {
        $command = [$this->mediaConfig->ffmpegBinaryPath];
        if ($this->mediaConfig->ffmpegThreads > 0) {
            $command[] = '-threads';
            $command[] = (string) $this->mediaConfig->ffmpegThreads;
        }

        $this->runFfmpegProcess([...$command, ...$arguments]);
    }

    /**
     * Сборка и запуск процесса ffmpeg с таймаутом из MediaConfig вынесены в отдельный метод, чтобы
     * юнит-тест подменил его в наследнике и проверил состав команды (наличие `-threads`) без
     * реального бинаря ffmpeg.
     *
     * @param list<string> $command полная команда: бинарь, опции потоков и аргументы вызова
     */
    protected function runFfmpegProcess(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout((float) $this->mediaConfig->ffmpegTimeoutSeconds);
        $process->mustRun();
    }

    /**
     * Читает числовое поле ffprobe (get() возвращает mixed на границе с библиотекой) как int.
     * Нечисловое значение для валидного медиа недостижимо; на всякий случай даёт 0 — границы
     * доменных VO (MediaPixelDimension/MediaBitrate) отклонят 0 как невалидный результат.
     */
    protected static function probeInt(AbstractData $data, string $key): int
    {
        $value = $data->get($key);

        return (int) (\is_numeric($value) ? $value : 0);
    }

    protected static function probeFloat(AbstractData $data, string $key): float
    {
        $value = $data->get($key);

        return (float) (\is_numeric($value) ? $value : 0);
    }

    protected static function classifyFailure(\Throwable $exception, string $message): MediaProcessorFailedException
    {
        // Guard-clause процессора уже бросил типизированное доменное исключение — пробрасываем как
        // есть, без повторной упаковки (иначе потерялись бы исходный признак и причина).
        if ($exception instanceof MediaProcessorFailedException) {
            return $exception;
        }

        return self::isTransientFailure($exception)
            ? MediaProcessorFailedException::transient(message: $message, previous: $exception)
            : MediaProcessorFailedException::permanent(message: $message, previous: $exception);
    }

    private static function isTransientFailure(\Throwable $exception): bool
    {
        // Таймаут транскодирования — единственный временный (повторяемый) сбой: ищем
        // ProcessTimedOutException в цепочке причин (php-ffmpeg оборачивает исключения процесса).
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ProcessTimedOutException) {
                return true;
            }
        }

        return false;
    }
}
