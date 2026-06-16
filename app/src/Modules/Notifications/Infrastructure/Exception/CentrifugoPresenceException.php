<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Exception;

/**
 * Сбой запроса presence-статистики к Centrifugo. Относится к инфраструктурному CentrifugoClient
 * и наружу в Application не выходит: CentrifugoOnlinePresence перехватывает его и трактует как
 * «не онлайн» (fail-open). Поэтому, в отличие от CentrifugoPublishException, нет флага isTransient —
 * исключение никогда не ретраится, флаг был бы мёртвым кодом.
 *
 * Слой исключения определяется контрактом, к которому оно относится: presence-исключение относится
 * к инфраструктурному CentrifugoClient, поэтому лежит в Infrastructure/Exception.
 */
final class CentrifugoPresenceException extends \DomainException
{
    private function __construct(string $message, \Throwable|null $previous = null)
    {
        parent::__construct(message: $message, previous: $previous);
    }

    public static function transport(\Throwable $previous): self
    {
        return new self(message: 'Centrifugo недоступен.', previous: $previous);
    }

    public static function serverError(int $statusCode): self
    {
        return new self(message: \sprintf('Centrifugo ответил ошибкой сервера (%d).', $statusCode));
    }

    public static function requestRejected(int $statusCode): self
    {
        return new self(message: \sprintf('Centrifugo отклонил запрос presence (%d).', $statusCode));
    }

    public static function apiError(int $code, string $message): self
    {
        return new self(message: \sprintf('Centrifugo отклонил presence (код %d: %s).', $code, $message));
    }

    public static function malformedResponse(): self
    {
        return new self(message: 'Centrifugo вернул некорректный ответ presence.');
    }
}
