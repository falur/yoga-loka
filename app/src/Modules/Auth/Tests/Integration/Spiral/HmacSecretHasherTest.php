<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Infrastructure\Spiral\Hash\HmacSecretHasher;
use PHPUnit\Framework\TestCase;
use Spiral\Encrypter\EncryptionInterface;

final class HmacSecretHasherTest extends TestCase
{
    public function testHashesDeterministicallyAndVerifies(): void
    {
        $hasher = $this->hasher('server-secret-key');

        $hash = $hasher->hash('123456');

        self::assertSame(\hash_hmac('sha256', '123456', 'server-secret-key'), $hash);
        self::assertSame($hash, $hasher->hash('123456'));
        self::assertTrue($hasher->verify('123456', $hash));
    }

    public function testRejectsWrongSecretOrTamperedHash(): void
    {
        $hasher = $this->hasher('server-secret-key');
        $hash = $hasher->hash('123456');

        self::assertFalse($hasher->verify('654321', $hash));
        self::assertFalse($hasher->verify('123456', $hash . 'x'));
    }

    private function hasher(string $key): HmacSecretHasher
    {
        $encryption = $this->createStub(EncryptionInterface::class);
        $encryption->method('getKey')->willReturn($key);

        return new HmacSecretHasher(encryption: $encryption);
    }
}
