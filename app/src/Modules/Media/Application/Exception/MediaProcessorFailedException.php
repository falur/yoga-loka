<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Exception;

/**
 * Сбой обработки медиа (ffmpeg) как часть контрактов MediaVideoProcessorContract /
 * MediaAudioProcessorContract. Несёт типизированный признак временной ошибки (`isTransient()`),
 * чтобы потребитель (ProcessMediaJob) выбирал стратегию повтора, не зная о конкретной реализации
 * (php-ffmpeg, бинарь ffmpeg).
 *
 * Слой исключения определяется контрактом, к которому оно относится (Application/Contract),
 * а не местом выброса: бросают его инфраструктурные ffmpeg-процессоры. Сообщения —
 * предопределённые безопасные строки; сырой вывод ffmpeg наружу не прокидывается.
 */
final class MediaProcessorFailedException extends \DomainException
{
    private function __construct(
        string $message,
        private readonly bool $transient,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(message: $message, previous: $previous);
    }

    /**
     * Временный (повторяемый) сбой обработки: таймаут транскодирования.
     */
    public static function transient(string $message, \Throwable $previous): self
    {
        return new self(message: $message, transient: true, previous: $previous);
    }

    /**
     * Постоянный сбой обработки: битый/неподдерживаемый вход, нештатный код выхода ffmpeg.
     */
    public static function permanent(string $message, \Throwable|null $previous = null): self
    {
        return new self(message: $message, transient: false, previous: $previous);
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }
}
