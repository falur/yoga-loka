<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Exception;

/**
 * Временный сбой отправки push через FCM (недоступность/ошибка сервера) как часть контракта
 * FcmPushSenderContract. isTransient() = true: Job переводит его в RetryException. Невалидные токены
 * сбоем не считаются — они возвращаются в FcmPushResult для удаления.
 *
 * Слой исключения определяется контрактом, к которому оно относится (Application/Contract),
 * а не местом выброса: бросает его инфраструктурная реализация KreaitFcmPushSender.
 */
final class FcmPushFailedException extends \DomainException
{
    private function __construct(string $message, private readonly bool $transient, \Throwable|null $previous = null)
    {
        parent::__construct(message: $message, previous: $previous);
    }

    public static function transient(\Throwable $previous): self
    {
        return new self(message: 'Не удалось отправить push через FCM.', transient: true, previous: $previous);
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }
}
