<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\User\Public\Contract\UserContract;
use App\Modules\User\Public\Dto\UserProfileDto;
use App\Modules\User\Public\Dto\UserProfileDtoCollection;
use App\Shared\Domain\Exception\ValidationException;

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
        private UserContract $users,
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
        if (!$this->users->existsAll($userIds)) {
            throw new ValidationException(self::MENTION_USER_NOT_FOUND_KEY);
        }
    }

    /**
     * Строгое разрешение профилей входного списка упоминаний для немедленной рассылки. Несуществующий
     * упомянутый -> 422 (валидация входа при создании опубликованной записи или комментария).
     *
     * @param list<string> $userIds
     */
    public function resolveRequired(array $userIds): UserProfileDtoCollection
    {
        if ($userIds === []) {
            return new UserProfileDtoCollection();
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
    public function resolveExisting(array $userIds): UserProfileDtoCollection
    {
        return $this->profiles($userIds);
    }

    public function profile(string $userId): UserProfileDto
    {
        return $this->users->profile($userId);
    }

    /**
     * @param list<string> $userIds
     */
    private function profiles(array $userIds): UserProfileDtoCollection
    {
        return $this->users->profilesByIds($userIds);
    }
}
