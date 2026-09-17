<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Client;

/**
 * Сбой запроса presence-статистики к Centrifugo. Относится к инфраструктурному CentrifugoClient
 * и наружу в Application не выходит: CentrifugoOnlinePresence перехватывает его и трактует как
 * «не онлайн» (fail-open). Поэтому, в отличие от CentrifugoPublishException, нет флага isTransient —
 * исключение никогда не ретраится, флаг был бы мёртвым кодом.
 *
 * Место исключения определяется технологией, к которой оно относится: presence-исключение
 * принадлежит границе внешнего клиента Centrifugo, поэтому лежит рядом с ним в
 * Infrastructure/Client, а не в отдельном разделе исключений.
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
