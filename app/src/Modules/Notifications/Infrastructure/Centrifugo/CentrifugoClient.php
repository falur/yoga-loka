<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Centrifugo;

use App\Modules\Notifications\Application\Dto\RealtimeNotificationPayload;
use App\Modules\Notifications\Application\Exception\CentrifugoPublishException;
use App\Modules\Notifications\Infrastructure\Exception\CentrifugoPresenceException;
use App\Shared\Infrastructure\Configuration\Centrifugo\CentrifugoConfig;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

/**
 * Низкоуровневый HTTP-клиент Centrifugo v6 на PSR-18: POST {apiUrl}/publish с заголовком
 * `Authorization: apikey <key>` и телом {channel, data}. Транспортные сбои и 5xx — временные,
 * 4xx — терминальный отказ запроса. Логические отказы публикации Centrifugo отдаёт кодом 200 с
 * объектом `error` в теле, поэтому при 2xx тело разбирается и наличие `error` тоже считается сбоем.
 * Запрос строится конструктором PSR-7 Guzzle (заголовки массивом), чтобы не зависеть от имён
 * параметров withHeader конкретной реализации.
 *
 * presenceStats() повторяет тот же HTTP-паттерн (POST {apiUrl}/presence_stats), но имеет свой
 * разбор тела и бросает только CentrifugoPresenceException: все режимы сбоя, включая битый JSON,
 * конвертируются в этот тип, потому что потребитель (CentrifugoOnlinePresence) полагается на
 * единственный тип исключения для fail-open. ensureNoApiError() не переиспользуется (чужой тип).
 */
final readonly class CentrifugoClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private CentrifugoConfig $config,
    ) {}

    public function publish(string $channel, RealtimeNotificationPayload $payload): void
    {
        $request = new Request(
            method: 'POST',
            uri: \sprintf('%s/publish', \rtrim(string: $this->config->apiUrl, characters: '/')),
            headers: [
                'Authorization' => \sprintf('apikey %s', $this->config->apiKey),
                'Content-Type' => 'application/json',
            ],
            body: $this->encodeBody(channel: $channel, payload: $payload),
        );

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw CentrifugoPublishException::transport($exception);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 500) {
            throw CentrifugoPublishException::serverError($statusCode);
        }

        if ($statusCode >= 400) {
            throw CentrifugoPublishException::requestRejected($statusCode);
        }

        $this->ensureNoApiError((string) $response->getBody());
    }

    /**
     * Запрашивает presence-статистику канала: POST {apiUrl}/presence_stats с телом {"channel": ...}.
     * Любой режим сбоя (транспорт, 5xx, 4xx, объект error в теле, битый JSON, неверная структура)
     * конвертируется в CentrifugoPresenceException — наружу не утекает другой тип исключения.
     */
    public function presenceStats(string $channel): CentrifugoPresenceStats
    {
        $request = new Request(
            method: 'POST',
            uri: \sprintf('%s/presence_stats', \rtrim(string: $this->config->apiUrl, characters: '/')),
            headers: [
                'Authorization' => \sprintf('apikey %s', $this->config->apiKey),
                'Content-Type' => 'application/json',
            ],
            body: \json_encode(
                value: ['channel' => $channel],
                flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
            ),
        );

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw CentrifugoPresenceException::transport($exception);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 500) {
            throw CentrifugoPresenceException::serverError($statusCode);
        }

        if ($statusCode >= 400) {
            throw CentrifugoPresenceException::requestRejected($statusCode);
        }

        return $this->parsePresenceStats((string) $response->getBody());
    }

    /**
     * Разбирает тело ответа presence_stats. Битый JSON (\JsonException) и неверную структуру
     * обязательно превращает в CentrifugoPresenceException::malformedResponse(), иначе исключение
     * утекло бы мимо перехвата в CentrifugoOnlinePresence и сломало бы fail-open (push не ушёл бы).
     */
    private function parsePresenceStats(string $body): CentrifugoPresenceStats
    {
        try {
            $decoded = \json_decode(json: $body, associative: true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw CentrifugoPresenceException::malformedResponse();
        }

        if (!\is_array($decoded)) {
            throw CentrifugoPresenceException::malformedResponse();
        }

        if (\array_key_exists(key: 'error', array: $decoded)) {
            $error = $decoded['error'];
            $code = \is_array($error) && \is_int($error['code'] ?? null) ? $error['code'] : 0;
            $message = \is_array($error) && \is_string($error['message'] ?? null) ? $error['message'] : 'неизвестная ошибка';

            throw CentrifugoPresenceException::apiError(code: $code, message: $message);
        }

        $result = $decoded['result'] ?? null;
        $numClients = \is_array($result) ? ($result['num_clients'] ?? null) : null;

        if (!\is_int($numClients)) {
            throw CentrifugoPresenceException::malformedResponse();
        }

        return new CentrifugoPresenceStats(numClients: $numClients);
    }

    /**
     * Centrifugo на логический отказ публикации (неизвестный канал/namespace, лимиты, отклонение
     * proxy) отвечает кодом 200 с объектом `error` в теле. Без разбора тела такой отказ молча
     * считался бы успехом и outbox-событие помечалось бы handled с потерей realtime-уведомления.
     */
    private function ensureNoApiError(string $body): void
    {
        if (\trim($body) === '') {
            return;
        }

        $decoded = \json_decode(json: $body, associative: true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded) || !\array_key_exists(key: 'error', array: $decoded)) {
            return;
        }

        $error = $decoded['error'];
        $code = \is_array($error) && \is_int($error['code'] ?? null) ? $error['code'] : 0;
        $message = \is_array($error) && \is_string($error['message'] ?? null) ? $error['message'] : 'неизвестная ошибка';

        throw CentrifugoPublishException::apiError(code: $code, message: $message);
    }

    private function encodeBody(string $channel, RealtimeNotificationPayload $payload): string
    {
        return \json_encode(
            value: ['channel' => $channel, 'data' => $payload],
            flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
