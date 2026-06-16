<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Автор уведомления — тот, кто его инициировал (например, пользователь, который подписался), как
 * явный тип вместо ?VO (rules.md:21). Снимок автора: ядро хранит не только userId, но и имя с
 * ссылкой на аватар, чтобы клиент показал автора без отдельного запроса к профилю. Одно
 * опциональное значение «есть/нет» -> один VO с приватным nullable внутри: автора нет (none())
 * либо есть (of()). Хранится в nullable json-колонке actor; на границах (Resource, payload,
 * typecast) раскладывается в {id, name, avatarUrl} либо null.
 */
final readonly class NotificationActor implements \JsonSerializable
{
    private const int MAX_NAME_LENGTH = 255;
    private const int MAX_AVATAR_URL_LENGTH = 2048;

    private function __construct(
        private UserId|null $userId,
        private string|null $name,
        private string|null $avatarUrl,
    ) {}

    public static function none(): self
    {
        return new self(userId: null, name: null, avatarUrl: null);
    }

    public static function of(UserId $userId, string $name, string $avatarUrl): self
    {
        return new self(
            userId: $userId,
            name: self::normalizeName($name),
            avatarUrl: self::normalizeAvatarUrl($avatarUrl),
        );
    }

    public function isPresent(): bool
    {
        return $this->userId !== null;
    }

    public function presentId(): string
    {
        if ($this->userId === null) {
            throw new InvalidDomainValueException('Автор уведомления отсутствует.');
        }

        return $this->userId->value();
    }

    public function presentName(): string
    {
        if ($this->name === null) {
            throw new InvalidDomainValueException('Имя автора уведомления отсутствует.');
        }

        return $this->name;
    }

    public function presentAvatarUrl(): string
    {
        if ($this->avatarUrl === null) {
            throw new InvalidDomainValueException('Ссылка на аватар автора уведомления отсутствует.');
        }

        return $this->avatarUrl;
    }

    public function equals(self $other): bool
    {
        return $this->userId?->value() === $other->userId?->value()
            && $this->name === $other->name
            && $this->avatarUrl === $other->avatarUrl;
    }

    /**
     * @return array<string, string>|null
     */
    #[\Override]
    public function jsonSerialize(): array|null
    {
        if (!$this->isPresent()) {
            return null;
        }

        return [
            'id' => $this->presentId(),
            'name' => $this->presentName(),
            'avatarUrl' => $this->presentAvatarUrl(),
        ];
    }

    private static function normalizeName(string $name): string
    {
        $name = \trim($name);

        if ($name === '' || \mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidDomainValueException('Имя автора уведомления имеет неверную длину.');
        }

        return $name;
    }

    private static function normalizeAvatarUrl(string $avatarUrl): string
    {
        $avatarUrl = \trim($avatarUrl);

        if ($avatarUrl === '' || \mb_strlen($avatarUrl) > self::MAX_AVATAR_URL_LENGTH) {
            throw new InvalidDomainValueException('Ссылка на аватар автора уведомления имеет неверную длину.');
        }

        return $avatarUrl;
    }
}
