<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\DatabaseDateTimeFormat;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<AuthToken>
 */
final class AuthTokenRepository extends Repository
{
    /**
     * Обычное чтение по хэшу (token_hash UNIQUE) — для load() на каждом запросе, без блокировки.
     */
    public function findByHash(TokenHash $tokenHash): AuthToken|null
    {
        return $this->select()
            ->where('token_hash', $tokenHash->value())
            ->fetchOne();
    }

    /**
     * Чтение по хэшу с блокировкой строки — для ротации refresh-токена.
     */
    public function findByHashForUpdate(TokenHash $tokenHash): AuthToken|null
    {
        return $this->select()
            ->where('token_hash', $tokenHash->value())
            ->forUpdate()
            ->fetchOne();
    }

    /**
     * Все токены сессии с блокировкой строк — для ротации и отзыва сессии.
     */
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
     * Все не истёкшие токены пользователя — для вывода списка его сессий. Read-only, без
     * блокировки. session_id DESC = новые сессии сверху (UUID v7 хронологичен). Оператор `>`
     * согласован с Expiration::isExpired (now >= value = истёк).
     */
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

    /**
     * Все токены конкретной сессии конкретного пользователя с блокировкой строк — для отзыва
     * сессии с проверкой владельца. Чужая сессия → пустая коллекция.
     */
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
}
