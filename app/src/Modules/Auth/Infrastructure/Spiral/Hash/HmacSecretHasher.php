<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Hash;

use App\Modules\Auth\Application\Contract\SecretHasherContract;
use Spiral\Encrypter\EncryptionInterface;

/**
 * HMAC-SHA256 хэширование низкоэнтропийных секретов (кодов/талонов) с серверным ключом
 * приложения (ENCRYPTER_KEY через EncryptionInterface::getKey()). При утечке БД без ключа
 * offline-перебор невозможен. Сравнение — constant-time.
 */
final readonly class HmacSecretHasher implements SecretHasherContract
{
    public function __construct(
        private EncryptionInterface $encryption,
    ) {}

    #[\Override]
    public function hash(string $secret): string
    {
        return \hash_hmac(algo: 'sha256', data: $secret, key: $this->encryption->getKey());
    }

    #[\Override]
    public function verify(string $secret, string $hash): bool
    {
        return \hash_equals(known_string: $this->hash($secret), user_string: $hash);
    }
}
