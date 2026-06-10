<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Exception;

/**
 * Сбой файлового сервиса как часть контракта MediaFileServiceContract. Несёт типизированный
 * признак транзиентности (`isTransient()`), чтобы потребитель контракта (ProcessMediaJob) выбирал
 * стратегию повтора, не зная о конкретной реализации хранилища (S3/AWS, локальный драйвер).
 *
 * Слой исключения определяется контрактом, к которому оно относится (Application/Contract),
 * а не местом выброса: бросает его инфраструктурная реализация S3MediaFileService.
 * Сообщения — предопределённые безопасные строки; сырой текст хранилища наружу не прокидывается.
 */
final class MediaFileServiceFailedException extends \DomainException
{
    private function __construct(
        string $message,
        private readonly bool $transient,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(message: $message, previous: $previous);
    }

    /**
     * Транзиентный (ретраябельный) сбой хранилища: сетевые ошибки, 5xx, throttling.
     */
    public static function transient(string $message, \Throwable $previous): self
    {
        return new self(message: $message, transient: true, previous: $previous);
    }

    /**
     * Постоянный сбой хранилища: нештатный ответ S3 или ошибка, не подлежащая повтору.
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
