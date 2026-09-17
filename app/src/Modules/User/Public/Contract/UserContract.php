<?php

declare(strict_types=1);

namespace App\Modules\User\Public\Contract;

use App\Modules\User\Public\Dto\CreatedUserDto;
use App\Modules\User\Public\Dto\UserProfileDto;
use App\Modules\User\Public\Dto\UserProfileDtoCollection;
use App\Modules\User\Public\Dto\UserSignInDto;

/**
 * Публичный контракт модуля User: единственная синхронная дверь соседей к пользователям.
 *
 * Создание вызывается изнутри транзакции соседа и остаётся вложенным: занятый email или никнейм —
 * это отказ всей операции (`app.user.email_taken`, `app.user.nickname_taken`, 422), откатывающий и
 * изменения вызывающего.
 *
 * Чтение профилей пакетное: набор идентификаторов на один ответ, а не вызов на каждого автора.
 * Пакетное чтение мягкое — несуществующий пользователь просто отсутствует в наборе, поэтому решение
 * «что делать с пропавшим» принимает потребитель. Одиночное чтение строгое: несуществующий
 * пользователь — `app.user.not_found` 404.
 */
interface UserContract
{
    public function createUser(string $email, string $name, string $nickname, string $locale): CreatedUserDto;

    /**
     * Пользователь по email для установления личности: null, если такого пользователя нет — тогда
     * сосед заводит регистрацию вместо входа.
     */
    public function findForSignIn(string $email): UserSignInDto|null;

    /**
     * Дешёвая проверка существования всего набора без сборки профилей: нужна там, где потребителю
     * достаточно факта наличия (валидация упоминаний черновика). Пустой набор существует.
     *
     * @param list<string> $userIds
     */
    public function existsAll(array $userIds): bool;

    public function profile(string $userId): UserProfileDto;

    /**
     * @param list<string> $userIds
     */
    public function profilesByIds(array $userIds): UserProfileDtoCollection;
}
