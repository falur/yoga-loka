<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Result;

use App\Modules\Media\Public\Dto\MediaDto;
use App\Modules\Posts\Domain\Exception\PostAuthorNotFoundException;
use App\Modules\User\Public\Dto\UserProfileDto;
use App\Modules\User\Public\Dto\UserProfileDtoCollection;

/**
 * Автор записи/комментария в ответе: снимок публичного профиля (id, имя и аватар с
 * преобразованиями одним значением). Аватар — публичное медиа модуля Media (оригинал + конверсии) или
 * null, если аватара нет: сервер не подставляет заглушку, дефолт ставит клиент.
 */
final readonly class AuthorResult
{
    public function __construct(
        public string $userId,
        public string $name,
        public MediaDto|null $avatar,
    ) {}

    public static function fromProfile(UserProfileDto $profile): self
    {
        return new self(
            userId: $profile->userId,
            name: $profile->name,
            avatar: $profile->avatar,
        );
    }

    /**
     * Пакетная карта профилей одним батчем -> карта авторов «идентификатор -> AuthorResult», чтобы
     * каждый вызывающий Query handler не повторял один и тот же цикл.
     *
     * @return array<string, self>
     */
    public static function mapFromProfiles(UserProfileDtoCollection $profiles): array
    {
        $authors = [];

        foreach ($profiles as $profile) {
            $authors[$profile->userId] = self::fromProfile($profile);
        }

        return $authors;
    }

    /**
     * Берёт автора из пакетной карты профилей. Пакетное чтение профилей молча опускает
     * отсутствующих, поэтому отсутствие ключа обрабатываем явно — той же 404, что и одиночный путь
     * (`UserContract::profile()`), а не неконтролируемым undefined array key -> 500. Ключ перевода
     * свой: текст совпадает с текстом владельца, но чужими ключами Posts не бросает.
     *
     * @param array<string, self> $authors
     */
    public static function require(array $authors, string $userId): self
    {
        return $authors[$userId] ?? throw new PostAuthorNotFoundException();
    }
}
