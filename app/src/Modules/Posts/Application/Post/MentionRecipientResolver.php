<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistHandler;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistQuery;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
use App\Shared\Domain\Exception\ValidationException;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Разрешение получателей упоминаний и сборка профилей для сценариев записей и комментариев.
 * Сводит воедино две роли, которые раньше дублировались в обоих Composer-ах, и разводит их по
 * назначению:
 *
 * - строгий путь (валидация входного списка при создании): клиент прислал идентификаторы упоминаний,
 *   несуществующий -> 422. requireAllExist() — дешёвая проверка наличия (черновик, рассылки нет),
 *   resolveRequired() — та же проверка плюс полные профили получателей для немедленной рассылки;
 * - мягкий путь (рассылка по уже сохранённым упоминаниям): упоминания уже отвалидированы при
 *   создании, к моменту рассылки кого-то могло не стать -> недоступные тихо пропускаются без 422
 *   (resolveExisting()). Так публикация черновика не падает на строгой проверке полноты.
 *
 * Ключ перевода ошибки упоминания и правило проверки полноты живут здесь, в одном месте.
 */
final readonly class MentionRecipientResolver
{
    private const string MENTION_USER_NOT_FOUND_KEY = 'app.posts.mention_user_not_found';

    public function __construct(
        private QueryBusInterface $queryBus,
        private CheckUsersExistHandler $checkUsersExistHandler,
        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
    ) {}

    /**
     * Строгая проверка существования входного списка упоминаний без сборки профилей. Несуществующий
     * упомянутый -> 422. Используется при создании черновика: упоминания валидируются, но рассылки
     * нет, поэтому полный профиль (с разрешением ссылки на аватар) собирать не нужно.
     *
     * @param list<string> $userIds
     */
    public function requireAllExist(array $userIds): void
    {
        $allExist = $this->queryBus->dispatch(
            query: new CheckUsersExistQuery($userIds),
            handler: $this->checkUsersExistHandler->handle(...),
        );

        if (!$allExist) {
            throw new ValidationException(self::MENTION_USER_NOT_FOUND_KEY);
        }
    }

    /**
     * Строгое разрешение профилей входного списка упоминаний для немедленной рассылки. Несуществующий
     * упомянутый -> 422 (валидация входа при создании опубликованной записи или комментария).
     *
     * @param list<string> $userIds
     */
    public function resolveRequired(array $userIds): UserPublicProfileCollection
    {
        if ($userIds === []) {
            return new UserPublicProfileCollection();
        }

        $recipients = $this->profiles($userIds);

        if ($recipients->count() !== \count($userIds)) {
            throw new ValidationException(self::MENTION_USER_NOT_FOUND_KEY);
        }

        return $recipients;
    }

    /**
     * Мягкое разрешение профилей по уже сохранённым упоминаниям: возвращает только существующих,
     * недоступных тихо пропускает без 422. Используется при публикации черновика, когда упоминания
     * уже были отвалидированы при создании.
     *
     * @param list<string> $userIds
     */
    public function resolveExisting(array $userIds): UserPublicProfileCollection
    {
        return $this->profiles($userIds);
    }

    public function profile(string $userId): UserPublicProfileView
    {
        return $this->queryBus->dispatch(
            query: new GetUserPublicProfileQuery($userId),
            handler: $this->getUserPublicProfileHandler->handle(...),
        );
    }

    /**
     * @param list<string> $userIds
     */
    private function profiles(array $userIds): UserPublicProfileCollection
    {
        return $this->queryBus->dispatch(
            query: new GetUserPublicProfilesQuery($userIds),
            handler: $this->getUserPublicProfilesHandler->handle(...),
        );
    }
}
