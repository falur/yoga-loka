<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
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
}
