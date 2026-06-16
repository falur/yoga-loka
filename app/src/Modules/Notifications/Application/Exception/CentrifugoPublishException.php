<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Exception;

/**
 * Сбой публикации в Centrifugo как часть контракта CentrifugoServiceContract. isTransient()
 * отделяет временную недоступность (повтор) от терминальной ошибки запроса (повтор не поможет).
 *
 * Слой исключения определяется контрактом, к которому оно относится (Application/Contract),
 * а не местом выброса: бросает его инфраструктурная реализация CentrifugoClient.
 */
final class CentrifugoPublishException extends \DomainException
{
    /**
     * Внутренний код ошибки Centrifugo (100) — временный сбой сервера, повтор может помочь.
     * Прочие коды (неизвестный канал/namespace, валидация, лимиты) терминальны.
     */
    private const INTERNAL_ERROR_CODE = 100;

    private function __construct(string $message, private readonly bool $transient, \Throwable|null $previous = null)
    {
        parent::__construct(message: $message, previous: $previous);
    }

    public static function transport(\Throwable $previous): self
    {
        return new self(message: 'Centrifugo недоступен.', transient: true, previous: $previous);
    }

    public static function serverError(int $statusCode): self
    {
        return new self(message: \sprintf('Centrifugo ответил ошибкой сервера (%d).', $statusCode), transient: true);
    }

    public static function requestRejected(int $statusCode): self
    {
        return new self(message: \sprintf('Centrifugo отклонил запрос публикации (%d).', $statusCode), transient: false);
    }

    public static function apiError(int $code, string $message): self
    {
        return new self(
            message: \sprintf('Centrifugo отклонил публикацию (код %d: %s).', $code, $message),
            transient: $code === self::INTERNAL_ERROR_CODE,
        );
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }
}
