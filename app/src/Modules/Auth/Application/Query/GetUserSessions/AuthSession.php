<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Query\GetUserSessions;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\Ip;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\UserAgent;
use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Read-model одной сессии пользователя: проекция группы токенов одной сессии (access + refresh),
 * а не доменный VO. Живёт рядом с Query (CQRS-контракт arch.md «Query Handler возвращает Result
 * DTO / типизированную коллекцию»). createdAt = самый ранний выпуск, expiresAt = самый поздний
 * срок (refresh, ~60 дней). ip/userAgent — из первого токена: оба токена сессии выпущены одним
 * issuePair, поэтому устройство идентично.
 */
final readonly class AuthSession
{
    public function __construct(
        public SessionId $sessionId,
        public \DateTimeImmutable $createdAt,
        public Expiration $expiresAt,
        public Ip $ip,
        public UserAgent $userAgent,
    ) {}

    public static function fromTokens(AuthTokenCollection $sessionTokens): self
    {
        $firstToken = $sessionTokens->first();

        if ($firstToken === null) {
            throw new InvalidDomainValueException('Сессия должна содержать хотя бы один токен.');
        }

        // Collection::min()/max() возвращают mixed (не проходят PHPStan strict при передаче в
        // Expiration::fromDateTime), поэтому считаем границы типизированным reduce по DateTimeImmutable.
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

        return new self(
            sessionId: $firstToken->sessionId,
            createdAt: $createdAt,
            expiresAt: Expiration::fromDateTime($expiresAt),
            ip: $firstToken->ip,
            userAgent: $firstToken->userAgent,
        );
    }
}
