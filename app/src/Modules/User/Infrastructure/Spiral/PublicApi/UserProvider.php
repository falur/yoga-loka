<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Spiral\PublicApi;

use App\Modules\User\Application\Command\CreateUser\CreateUserCommand;
use App\Modules\User\Application\Command\CreateUser\CreateUserHandler;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistHandler;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistQuery;
use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthHandler;
use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthQuery;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
use App\Modules\User\Public\Contract\UserContract;
use App\Modules\User\Public\Dto\CreatedUserDto;
use App\Modules\User\Public\Dto\UserProfileDto;
use App\Modules\User\Public\Dto\UserProfileDtoCollection;
use App\Modules\User\Public\Dto\UserSignInDto;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Входной адаптер публичного контракта User: раскладывает вызовы соседей в существующие сценарии
 * модуля и переводит их внутренние формы ответа в публичные DTO. Правил здесь нет — занятый email,
 * право входа, отсутствие пользователя и разрешение аватара остаются в сценариях.
 *
 * Создание диспатчится командной шиной, поэтому внутри транзакции соседа остаётся вложенным
 * (#[Transactional] -> SAVEPOINT) — так же, как до появления контракта.
 */
final readonly class UserProvider implements UserContract
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private CreateUserHandler $createUserHandler,
        private FindUserForAuthHandler $findUserForAuthHandler,
        private CheckUsersExistHandler $checkUsersExistHandler,
        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
    ) {}

    #[\Override]
    public function createUser(string $email, string $name, string $nickname, string $locale): CreatedUserDto
    {
        $result = $this->commandBus->dispatch(
            command: new CreateUserCommand(email: $email, name: $name, nickname: $nickname, locale: $locale),
            handler: $this->createUserHandler->handle(...),
        );

        return new CreatedUserDto(userId: $result->userId);
    }

    #[\Override]
    public function findForSignIn(string $email): UserSignInDto|null
    {
        $authView = $this->queryBus->dispatch(
            query: new FindUserForAuthQuery(email: $email),
            handler: $this->findUserForAuthHandler->handle(...),
        );

        if ($authView === null) {
            return null;
        }

        return new UserSignInDto(userId: $authView->userId, canSignIn: $authView->canSignIn);
    }

    /**
     * @param list<string> $userIds
     */
    #[\Override]
    public function existsAll(array $userIds): bool
    {
        return $this->queryBus->dispatch(
            query: new CheckUsersExistQuery(userIds: $userIds),
            handler: $this->checkUsersExistHandler->handle(...),
        );
    }

    #[\Override]
    public function profile(string $userId): UserProfileDto
    {
        return $this->profileDto($this->queryBus->dispatch(
            query: new GetUserPublicProfileQuery(userId: $userId),
            handler: $this->getUserPublicProfileHandler->handle(...),
        ));
    }

    /**
     * @param list<string> $userIds
     */
    #[\Override]
    public function profilesByIds(array $userIds): UserProfileDtoCollection
    {
        $profiles = $this->queryBus->dispatch(
            query: new GetUserPublicProfilesQuery(userIds: $userIds),
            handler: $this->getUserPublicProfilesHandler->handle(...),
        );

        return new UserProfileDtoCollection(
            $profiles->toBase()
                ->map(fn(UserPublicProfileView $profile): UserProfileDto => $this->profileDto($profile))
                ->keyBy(static fn(UserProfileDto $profile): string => $profile->userId),
        );
    }

    private function profileDto(UserPublicProfileView $profile): UserProfileDto
    {
        return new UserProfileDto(
            userId: $profile->userId,
            name: $profile->name,
            avatar: $profile->avatar,
            locale: $profile->locale,
        );
    }
}
