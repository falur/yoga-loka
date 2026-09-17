<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Автор уведомления — тот, кто его инициировал (например, пользователь, который подписался), как
 * явный тип вместо ?VO (rules.md:21). Снимок автора: ядро хранит userId, имя и id медиа-аватара, чтобы
 * клиент показал автора без отдельного запроса к профилю. Одно опциональное значение «есть/нет» -> один
 * VO с приватным nullable внутри: автора нет (none()) либо есть (of()).
 *
 * Аватар хранится как id медиа (avatarMediaId), а не как готовая ссылка: саму ссылку (полное медиа с
 * оригиналом и конверсиями) потребитель собирает заново в момент показа через модуль Media, поэтому она
 * не протухает (у private-медиа presigned-ссылки временные) и не выдумывается сервером. Аватар
 * необязателен: если у профиля его нет, поле пустое (null). Хранится в nullable json-колонке actor; на
 * границах (Resource, payload, typecast) раскладывается в {id, name, avatarMediaId} (avatarMediaId может
 * быть null) либо null целиком.
 */
final readonly class NotificationActor implements \JsonSerializable
{
    private const int MAX_NAME_LENGTH = 255;

    private function __construct(
        private UserId|null $userId,
        private string|null $name,
        private string|null $avatarMediaId,
    ) {}

    public static function none(): self
    {
        return new self(userId: null, name: null, avatarMediaId: null);
    }

    public static function of(UserId $userId, string $name, string|null $avatarMediaId): self
    {
        return new self(
            userId: $userId,
            name: self::normalizeName($name),
            avatarMediaId: self::normalizeAvatarMediaId($avatarMediaId),
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

    /**
     * Id медиа-аватара присутствующего автора или null, если аватара нет. Guard — на отсутствие
     * самого автора (none()), а не аватара: у present-автора аватар опционален.
     */
    public function presentAvatarMediaId(): string|null
    {
        if ($this->userId === null) {
            throw new InvalidDomainValueException('Автор уведомления отсутствует.');
        }

        return $this->avatarMediaId;
    }

    public function equals(self $other): bool
    {
        return $this->userId?->value() === $other->userId?->value()
            && $this->name === $other->name
            && $this->avatarMediaId === $other->avatarMediaId;
    }

    /**
     * @return array<string, string|null>|null
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
            'avatarMediaId' => $this->presentAvatarMediaId(),
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

    /**
     * Аватар опционален: null -> «аватара нет» (null). Непустой id валидируем как UUID v7 медиа —
     * иначе это внутреннее нарушение (500), а не пользовательская ошибка.
     */
    private static function normalizeAvatarMediaId(string|null $avatarMediaId): string|null
    {
        if ($avatarMediaId === null) {
            return null;
        }

        if (!AbstractUuidV7Id::isUuidV7($avatarMediaId)) {
            throw new InvalidDomainValueException('Аватар автора уведомления должен ссылаться на UUID v7 медиа.');
        }

        return \strtolower($avatarMediaId);
    }
}
