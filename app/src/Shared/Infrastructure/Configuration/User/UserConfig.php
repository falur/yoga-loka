<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\User;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;

final readonly class UserConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'user';
    }

    public function __construct(
        public string $defaultAvatarUrl,
    ) {
        // Дефолтный URL аватара обязан быть непустым: публичный профиль подставляет его, когда у
        // пользователя нет аватара, а NotificationActor::of() бросает на пустой avatarUrl.
        if (\trim($defaultAvatarUrl) === '') {
            throw new InvalidConfigValueException(
                path: 'user.defaultAvatarUrl',
                expected: 'непустой URL аватара по умолчанию',
                actual: $defaultAvatarUrl,
            );
        }
    }
}
