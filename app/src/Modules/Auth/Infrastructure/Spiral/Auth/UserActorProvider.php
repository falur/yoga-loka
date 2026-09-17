<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Auth;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Shared\Domain\ValueObject\UserId;
use Spiral\Auth\ActorProviderInterface;
use Spiral\Auth\TokenInterface;

/**
 * Провайдер актора для HTTP-аутентификации. Возвращает актора ТОЛЬКО для access-токена:
 * refresh-токен, присланный как Bearer, актора не даёт (закрытая дыра — иначе один load()
 * пропустил бы refresh как сессию).
 */
final readonly class UserActorProvider implements ActorProviderInterface
{
    #[\Override]
    public function getActor(TokenInterface $token): object|null
    {
        $payload = $token->getPayload();

        if (($payload['type'] ?? null) !== AuthTokenType::Access->value) {
            return null;
        }

        $userId = $payload['userID'] ?? null;

        if (!\is_string($userId)) {
            return null;
        }

        return new AuthenticatedUser(userId: UserId::fromString($userId));
    }
}
