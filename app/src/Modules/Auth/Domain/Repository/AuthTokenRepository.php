<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Repository;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение выданных токенов. Корень агрегата — AuthToken, внутренних сущностей у него нет.
 * Сессия здесь не отдельный агрегат, а набор токенов с общим sessionId, поэтому отзыв сессии
 * выражен удалением такого набора одним вызовом.
 */
interface AuthTokenRepository
{
    /**
     * Чтение по хэшу без блокировки — для установления личности на каждом запросе.
     */
    public function findByHash(TokenHash $tokenHash): AuthToken|null;

    /**
     * Чтение по хэшу с блокировкой строки — для ротации refresh-токена.
     */
    public function findByHashForUpdate(TokenHash $tokenHash): AuthToken|null;

    /**
     * Все токены сессии с блокировкой строк — для ротации и отзыва сессии.
     */
    public function findBySessionIdForUpdate(SessionId $sessionId): AuthTokenCollection;

    /**
     * Все не истёкшие токены пользователя — для вывода списка его сессий, без блокировки.
     */
    public function findActiveByUserId(UserId $userId, \DateTimeImmutable $now): AuthTokenCollection;

    /**
     * Все токены конкретной сессии конкретного пользователя с блокировкой строк — для отзыва
     * сессии с проверкой владельца. Чужая сессия даёт пустую коллекцию.
     */
    public function findByUserAndSessionForUpdate(UserId $userId, SessionId $sessionId): AuthTokenCollection;

    /**
     * Сохраняет токен своим прогоном: вместе с ним в базу уходит всё, что уже поставлено
     * в текущую запись.
     */
    public function save(AuthToken $authToken): void;

    /**
     * Удаляет один токен своим прогоном.
     */
    public function delete(AuthToken $authToken): void;

    /**
     * Удаляет набор токенов одним прогоном на весь набор, а не по прогону на токен.
     */
    public function deleteAll(AuthTokenCollection $authTokens): void;
}
