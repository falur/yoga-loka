<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\DatabaseDateTimeFormat;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<AuthToken>
 */
final class CycleAuthTokenRepository extends AbstractRepository implements AuthTokenRepository
{
    /**
     * @param Select<AuthToken> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findByHash(TokenHash $tokenHash): AuthToken|null
    {
        return $this->select()
            ->where('token_hash', $tokenHash->value())
            ->fetchOne();
    }

    #[\Override]
    public function findByHashForUpdate(TokenHash $tokenHash): AuthToken|null
    {
        return $this->select()
            ->where('token_hash', $tokenHash->value())
            ->forUpdate()
            ->fetchOne();
    }

    #[\Override]
    public function findBySessionIdForUpdate(SessionId $sessionId): AuthTokenCollection
    {
        return new AuthTokenCollection(
            $this->select()
                ->where('session_id', $sessionId->value())
                ->forUpdate()
                ->fetchAll(),
        );
    }

    /**
     * session_id DESC = новые сессии сверху (UUID v7 хронологичен). Оператор `>` согласован
     * с Expiration::isExpired (now >= value = истёк).
     */
    #[\Override]
    public function findActiveByUserId(UserId $userId, \DateTimeImmutable $now): AuthTokenCollection
    {
        return new AuthTokenCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->where('expires_at', '>', $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS))
                ->orderBy(expression: 'session_id', direction: 'DESC')
                ->orderBy(expression: 'created_at')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findByUserAndSessionForUpdate(UserId $userId, SessionId $sessionId): AuthTokenCollection
    {
        return new AuthTokenCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->where('session_id', $sessionId->value())
                ->forUpdate()
                ->fetchAll(),
        );
    }

    #[\Override]
    public function save(AuthToken $authToken): void
    {
        $this->entityManager
            ->persist($authToken)
            ->run();
    }

    #[\Override]
    public function delete(AuthToken $authToken): void
    {
        $this->entityManager
            ->delete($authToken)
            ->run();
    }

    #[\Override]
    public function deleteAll(AuthTokenCollection $authTokens): void
    {
        foreach ($authTokens as $authToken) {
            $this->entityManager->delete($authToken);
        }

        $this->entityManager->run();
    }
}
