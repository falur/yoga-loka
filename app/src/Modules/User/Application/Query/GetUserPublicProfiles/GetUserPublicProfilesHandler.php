<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserPublicProfiles;

use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Публичные профили нескольких пользователей (авторы ленты/комментариев, проверка упоминаний).
 * Несуществующие идентификаторы просто отсутствуют в результате — вызывающий сам сверяет
 * запрошенные и найденные, чтобы при необходимости вернуть 422 на несуществующее упоминание.
 *
 * Аватары всего набора ассемблер разрешает одним обращением к Media, поэтому число вызовов соседа не
 * зависит от числа пользователей в наборе.
 */
final readonly class GetUserPublicProfilesHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPublicProfileAssembler $assembler,
    ) {}

    #[LogOperation]
    public function handle(GetUserPublicProfilesQuery $query): UserPublicProfileCollection
    {
        $userIds = \array_map(
            static fn(string $userId): UserId => UserId::fromString($userId),
            $query->userIds,
        );

        return $this->assembler->fromUsers($this->userRepository->findByIds(...$userIds));
    }
}
