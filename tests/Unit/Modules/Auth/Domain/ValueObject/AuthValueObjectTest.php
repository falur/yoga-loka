<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Domain\ValueObject;

use App\Modules\Auth\Domain\ValueObject\CodeAttempts;
use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\User\Domain\ValueObject\Email;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthValueObjectTest extends TestCase
{
    public function testEmailAddressNormalizesAndSerializes(): void
    {
        $email = EmailAddress::fromString(' TEST@Example.COM ');

        self::assertSame('test@example.com', $email->value());
        self::assertTrue($email->equals(EmailAddress::fromString('test@example.com')));
        self::assertSame('test@example.com', (string) $email);
        self::assertSame('test@example.com', $email->jsonSerialize());
    }

    public function testEmailAddressRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        EmailAddress::fromString('   ');
    }

    public function testEmailAddressRejectsInvalidValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        EmailAddress::fromString('not-email');
    }

    public function testEmailAddressRejectsTooLongValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        EmailAddress::fromString(\str_repeat('a', 245) . '@example.com');
    }

    /**
     * Инвариант: нормализация EmailAddress (Auth) обязана совпадать с User\Email,
     * иначе ключи по email в auth_* и users разойдутся.
     */
    #[DataProvider('emailEquivalenceProvider')]
    public function testEmailAddressNormalizationMatchesUserEmail(string $rawEmail): void
    {
        self::assertSame(
            Email::fromString($rawEmail)->value(),
            EmailAddress::fromString($rawEmail)->value(),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emailEquivalenceProvider(): array
    {
        return [
            'нижний регистр' => ['user@example.com'],
            'верхний регистр и пробелы' => ['  USER@Example.COM  '],
            'смешанный регистр' => ['MixedCase@Domain.Org'],
            'плюс-адресация' => ['user+tag@example.com'],
        ];
    }

    public function testSecretHashStoresAndCompares(): void
    {
        $secretHash = SecretHash::fromString('hmac-value');

        self::assertSame('hmac-value', $secretHash->value());
        self::assertSame('hmac-value', (string) $secretHash);
        self::assertSame('hmac-value', $secretHash->jsonSerialize());
        self::assertTrue($secretHash->equals(SecretHash::fromString('hmac-value')));
        self::assertFalse($secretHash->equals(SecretHash::fromString('other')));
    }

    public function testSecretHashRejectsEmptyValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        SecretHash::fromString('');
    }

    public function testTokenHashHashesRawTokenAndCompares(): void
    {
        $tokenHash = TokenHash::fromRawToken('raw-token-value');

        self::assertSame(\hash('sha256', 'raw-token-value'), $tokenHash->value());
        self::assertSame($tokenHash->value(), (string) $tokenHash);
        self::assertSame($tokenHash->value(), $tokenHash->jsonSerialize());
        self::assertTrue($tokenHash->equals(TokenHash::fromRawToken('raw-token-value')));
        self::assertFalse($tokenHash->equals(TokenHash::fromRawToken('another')));
        self::assertTrue($tokenHash->equals(TokenHash::fromString(\hash('sha256', 'raw-token-value'))));
    }

    public function testTokenHashRejectsEmptyRawToken(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        TokenHash::fromRawToken('');
    }

    public function testTokenHashRejectsEmptyStoredHash(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        TokenHash::fromString('');
    }

    public function testCodeAttemptsCountsAndExhausts(): void
    {
        $attempts = CodeAttempts::initial();

        self::assertSame(0, $attempts->value());
        self::assertFalse($attempts->isExhausted());
        self::assertSame('0', (string) $attempts);
        self::assertSame(0, $attempts->jsonSerialize());

        $incremented = $attempts->increment();

        self::assertSame(1, $incremented->value());
        self::assertTrue($incremented->equals(CodeAttempts::fromInt(1)));
        self::assertFalse($incremented->equals($attempts));

        self::assertFalse(CodeAttempts::fromInt(4)->isExhausted());
        self::assertTrue(CodeAttempts::fromInt(5)->isExhausted());
        self::assertTrue(CodeAttempts::fromInt(6)->isExhausted());
    }

    public function testCodeAttemptsRejectsNegativeValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CodeAttempts::fromInt(-1);
    }

    public function testExpirationComputesAndChecksExpiry(): void
    {
        $now = new \DateTimeImmutable('2026-06-15 12:00:00');
        $expiration = Expiration::after($now, 600);

        self::assertEquals($now->add(new \DateInterval('PT600S')), $expiration->value());
        self::assertFalse($expiration->isExpired($now));
        self::assertFalse($expiration->isExpired($now->add(new \DateInterval('PT599S'))));
        self::assertTrue($expiration->isExpired($now->add(new \DateInterval('PT600S'))));
        self::assertTrue($expiration->isExpired($now->add(new \DateInterval('PT601S'))));
        self::assertSame($expiration->value()->format(\DateTimeInterface::ATOM), (string) $expiration);
        self::assertSame($expiration->value()->format(\DateTimeInterface::ATOM), $expiration->jsonSerialize());
    }

    public function testExpirationEquality(): void
    {
        $moment = new \DateTimeImmutable('2026-06-15 12:00:00');

        self::assertTrue(Expiration::fromDateTime($moment)->equals(Expiration::fromDateTime($moment)));
        self::assertFalse(
            Expiration::fromDateTime($moment)->equals(
                Expiration::fromDateTime($moment->add(new \DateInterval('PT1S'))),
            ),
        );
    }

    public function testConsumptionNullObject(): void
    {
        $notConsumed = Consumption::notConsumed();

        self::assertFalse($notConsumed->isConsumed());
        self::assertNull($notConsumed->value());
        self::assertSame('', (string) $notConsumed);
        self::assertNull($notConsumed->jsonSerialize());

        $moment = new \DateTimeImmutable('2026-06-15 12:00:00');
        $consumed = Consumption::at($moment);

        self::assertTrue($consumed->isConsumed());
        self::assertSame($moment, $consumed->value());
        self::assertSame($moment->format(\DateTimeInterface::ATOM), (string) $consumed);
        self::assertSame($moment->format(\DateTimeInterface::ATOM), $consumed->jsonSerialize());
    }

    public function testConsumptionEquality(): void
    {
        $moment = new \DateTimeImmutable('2026-06-15 12:00:00');

        self::assertTrue(Consumption::notConsumed()->equals(Consumption::notConsumed()));
        self::assertFalse(Consumption::notConsumed()->equals(Consumption::at($moment)));
        self::assertFalse(Consumption::at($moment)->equals(Consumption::notConsumed()));
        self::assertTrue(Consumption::at($moment)->equals(Consumption::at($moment)));
        self::assertFalse(
            Consumption::at($moment)->equals(Consumption::at($moment->add(new \DateInterval('PT1S')))),
        );
    }
}
