<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\View;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use Illuminate\Support\Collection;

/**
 * Собирает read-model активных сессий: группирует токены по sessionId (access + refresh одной
 * сессии) и сворачивает каждую группу в SessionView. Группировка идёт через базовый Collection,
 * чтобы дженерики AuthTokenCollection не ломали PHPStan на вложенном результате groupBy.
 * createdAt = самый ранний выпуск, expiresAt = самый поздний срок (refresh, ~60 дней). ip/userAgent
 * берём из первого токена: оба токена сессии выпущены одним issuePair, поэтому устройство идентично.
 */
final readonly class SessionViewAssembler
{
    public function fromActiveTokens(AuthTokenCollection $activeTokens, string $currentSessionId): SessionViewCollection
    {
        return new SessionViewCollection(
            $activeTokens
                ->toBase()
                ->groupBy(static fn(AuthToken $token): string => $token->sessionId->value())
                ->map(fn(Collection $sessionTokens): SessionView => $this->fromSessionTokens(
                    sessionTokens: new AuthTokenCollection($sessionTokens),
                    currentSessionId: $currentSessionId,
                ))
                ->values(),
        );
    }

    public function fromSessionTokens(AuthTokenCollection $sessionTokens, string $currentSessionId): SessionView
    {
        $firstToken = $sessionTokens->first();

        if ($firstToken === null) {
            throw new InvalidDomainValueException('Сессия должна содержать хотя бы один токен.');
        }

        // Collection::min()/max() возвращают mixed (не проходят PHPStan strict), поэтому границы
        // считаем типизированным reduce по DateTimeImmutable.
        $createdAt = $sessionTokens->reduce(
            callback: static fn(\DateTimeImmutable $earliest, AuthToken $token): \DateTimeImmutable
                => $token->createdAt < $earliest ? $token->createdAt : $earliest,
            initial: $firstToken->createdAt,
        );
        $expiresAt = $sessionTokens->reduce(
            callback: static fn(\DateTimeImmutable $latest, AuthToken $token): \DateTimeImmutable
                => $token->expiration->value() > $latest ? $token->expiration->value() : $latest,
            initial: $firstToken->expiration->value(),
        );

        return new SessionView(
            id: $firstToken->sessionId->value(),
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            ip: $firstToken->ip->toNullableString(),
            device: $firstToken->userAgent->toNullableString(),
            current: $firstToken->sessionId->value() === $currentSessionId,
        );
    }
}
