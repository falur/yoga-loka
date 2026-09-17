<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns\AuthTokenColumns;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity\CycleAuthTokenEntity;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\AuthTokenMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use App\Shared\Infrastructure\Persistence\Cycle\DatabaseDateTimeFormat;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleAuthTokenEntity>
 */
final class CycleAuthTokenRepository extends AbstractRepository implements AuthTokenRepository
{
    /**
     * @param Select<CycleAuthTokenEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private AuthTokenMapper $authTokenMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findByHash(TokenHash $tokenHash): AuthToken|null
    {
        /** @var CycleAuthTokenEntity|null $cycleEntity */
        $cycleEntity = $this->select()
            ->where(AuthTokenColumns::TOKEN_HASH, $tokenHash->value())
            ->fetchOne();

        return $cycleEntity === null ? null : $this->authTokenMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByHashForUpdate(TokenHash $tokenHash): AuthToken|null
    {
        /** @var CycleAuthTokenEntity|null $cycleEntity */
        $cycleEntity = $this->select()
            ->where(AuthTokenColumns::TOKEN_HASH, $tokenHash->value())
            ->forUpdate()
            ->fetchOne();

        return $cycleEntity === null ? null : $this->authTokenMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findBySessionIdForUpdate(SessionId $sessionId): AuthTokenCollection
    {
        $authTokenCollection = new AuthTokenCollection();

        /** @var iterable<CycleAuthTokenEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(AuthTokenColumns::SESSION_ID, $sessionId->value())
            ->forUpdate()
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $authTokenCollection->push($this->authTokenMapper->toDomain($cycleEntity));
        }

        return $authTokenCollection;
    }

    /**
     * session_id DESC = новые сессии сверху (UUID v7 хронологичен). Оператор `>` согласован
     * с Expiration::isExpired (now >= value = истёк).
     */
    #[\Override]
    public function findActiveByUserId(UserId $userId, \DateTimeImmutable $now): AuthTokenCollection
    {
        $authTokenCollection = new AuthTokenCollection();

        /** @var iterable<CycleAuthTokenEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(AuthTokenColumns::USER_ID, $userId->value())
            ->where(AuthTokenColumns::EXPIRES_AT, '>', $now->format(DatabaseDateTimeFormat::WITH_MICROSECONDS))
            ->orderBy(expression: AuthTokenColumns::SESSION_ID, direction: 'DESC')
            ->orderBy(expression: AuthTokenColumns::CREATED_AT)
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $authTokenCollection->push($this->authTokenMapper->toDomain($cycleEntity));
        }

        return $authTokenCollection;
    }

    #[\Override]
    public function findByUserAndSessionForUpdate(UserId $userId, SessionId $sessionId): AuthTokenCollection
    {
        $authTokenCollection = new AuthTokenCollection();

        /** @var iterable<CycleAuthTokenEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(AuthTokenColumns::USER_ID, $userId->value())
            ->where(AuthTokenColumns::SESSION_ID, $sessionId->value())
            ->forUpdate()
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $authTokenCollection->push($this->authTokenMapper->toDomain($cycleEntity));
        }

        return $authTokenCollection;
    }

    #[\Override]
    public function save(AuthToken $authToken): void
    {
        /** @var CycleAuthTokenEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([AuthTokenColumns::ID => $authToken->id->value()]);

        $this->entityManager
            ->persist($this->authTokenMapper->toCycleEntity(
                authToken: $authToken,
                cycleEntity: $cycleEntity,
            ))
            ->run();
    }

    #[\Override]
    public function delete(AuthToken $authToken): void
    {
        /** @var CycleAuthTokenEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([AuthTokenColumns::ID => $authToken->id->value()]);

        if ($cycleEntity === null) {
            return;
        }

        $this->entityManager
            ->delete($cycleEntity)
            ->run();
    }

    #[\Override]
    public function deleteAll(AuthTokenCollection $authTokens): void
    {
        if (!$authTokens->isEmpty()) {
            /** @var iterable<CycleAuthTokenEntity> $cycleEntities */
            $cycleEntities = $this->select()
                ->where(AuthTokenColumns::ID, 'in', new Parameter($authTokens->mapToList(
                    static fn(AuthToken $authToken): string => $authToken->id->value(),
                )))
                ->fetchAll();

            foreach ($cycleEntities as $cycleEntity) {
                $this->entityManager->delete($cycleEntity);
            }
        }

        $this->entityManager->run();
    }
}
