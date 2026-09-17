<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Application\Contract\SecretHasherContract;

/**
 * Детерминированный хэшер для тестов сценариев: изолирует логику Auth/Application от реального
 * HMAC и ключа шифрования (HmacSecretHasher покрыт отдельно в фазе 3).
 */
final readonly class FakeSecretHasher implements SecretHasherContract
{
    #[\Override]
    public function hash(string $secret): string
    {
        return 'hashed:' . $secret;
    }

    #[\Override]
    public function verify(string $secret, string $hash): bool
    {
        return \hash_equals(known_string: $hash, user_string: 'hashed:' . $secret);
    }
}
