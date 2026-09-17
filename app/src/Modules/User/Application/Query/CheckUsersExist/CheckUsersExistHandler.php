<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\CheckUsersExist;

use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Проверка существования всех переданных пользователей по идентификаторам. Возвращает true только
 * если каждый запрошенный идентификатор найден. Дешевле GetUserPublicProfiles: считает строки в БД
 * и не собирает профиль с разрешением ссылки на аватар через Media. Нужна для валидации упоминаний
 * без рассылки (черновик), где полный профиль не используется.
 */
final readonly class CheckUsersExistHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[LogOperation]
    public function handle(CheckUsersExistQuery $query): bool
    {
        $userIds = \array_map(
            static fn(string $userId): UserId => UserId::fromString($userId),
            \array_values(\array_unique($query->userIds)),
        );

        if ($userIds === []) {
            return true;
        }

        return $this->userRepository->countByIds(...$userIds) === \count($userIds);
    }
}
