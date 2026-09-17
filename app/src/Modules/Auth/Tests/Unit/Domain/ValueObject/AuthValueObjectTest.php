<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit\Domain\ValueObject;

use App\Modules\Auth\Domain\ValueObject\CodeAttempts;
use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\Ip;
use App\Modules\Auth\Domain\ValueObject\KnownIp;
use App\Modules\Auth\Domain\ValueObject\KnownUserAgent;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Domain\ValueObject\UnknownIp;
use App\Modules\Auth\Domain\ValueObject\UnknownUserAgent;
use App\Modules\Auth\Domain\ValueObject\UserAgent;
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

    #[DataProvider('validIpProvider')]
    public function testKnownIpAcceptsValidAddresses(string $rawIp): void
    {
        $ip = Ip::fromNullable($rawIp);

        self::assertInstanceOf(KnownIp::class, $ip);
        self::assertSame($rawIp, $ip->value());
        self::assertSame($rawIp, $ip->toNullableString());
        self::assertSame($rawIp, $ip->jsonSerialize());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validIpProvider(): array
    {
        return [
            'IPv4' => ['203.0.113.7'],
            'IPv6' => ['2001:db8::1'],
        ];
    }

    #[DataProvider('absentIpProvider')]
    public function testUnknownIpForNullEmptyOrInvalid(string|null $rawIp): void
    {
        $ip = Ip::fromNullable($rawIp);

        self::assertInstanceOf(UnknownIp::class, $ip);
        self::assertNull($ip->toNullableString());
        self::assertNull($ip->jsonSerialize());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function absentIpProvider(): array
    {
        return [
            'null' => [null],
            'пусто' => [''],
            'пробелы' => ['   '],
            'не IP' => ['not-an-ip'],
        ];
    }

    public function testKnownIpTrimsAndRejectsInvalid(): void
    {
        self::assertSame('203.0.113.7', KnownIp::fromString('  203.0.113.7  ')->value());

        $this->expectException(InvalidDomainValueException::class);

        KnownIp::fromString('999.999.999.999');
    }

    public function testIpEquality(): void
    {
        $knownIp = Ip::fromNullable('203.0.113.7');
        $unknownIp = Ip::fromNullable(null);

        self::assertTrue($knownIp->equals(Ip::fromNullable('203.0.113.7')));
        self::assertFalse($knownIp->equals(Ip::fromNullable('198.51.100.1')));
        self::assertFalse($knownIp->equals($unknownIp));
        self::assertTrue($unknownIp->equals(Ip::fromNullable('')));
        self::assertFalse($unknownIp->equals($knownIp));
    }

    public function testKnownUserAgentNormalizesAndTruncates(): void
    {
        $userAgent = UserAgent::fromNullable('  Mozilla/5.0 Test  ');

        self::assertInstanceOf(KnownUserAgent::class, $userAgent);
        self::assertSame('Mozilla/5.0 Test', $userAgent->value());
        self::assertSame('Mozilla/5.0 Test', $userAgent->toNullableString());
        self::assertSame('Mozilla/5.0 Test', $userAgent->jsonSerialize());

        $longUserAgent = UserAgent::fromNullable(\str_repeat('a', 2000));

        self::assertInstanceOf(KnownUserAgent::class, $longUserAgent);
        self::assertSame(1024, \mb_strlen((string) $longUserAgent->value()));
    }

    #[DataProvider('absentUserAgentProvider')]
    public function testUnknownUserAgentForNullOrEmpty(string|null $rawUserAgent): void
    {
        $userAgent = UserAgent::fromNullable($rawUserAgent);

        self::assertInstanceOf(UnknownUserAgent::class, $userAgent);
        self::assertNull($userAgent->toNullableString());
        self::assertNull($userAgent->jsonSerialize());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function absentUserAgentProvider(): array
    {
        return [
            'null' => [null],
            'пусто' => [''],
            'пробелы' => ['   '],
        ];
    }

    public function testKnownUserAgentRejectsBlankValue(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        KnownUserAgent::fromString('   ');
    }

    public function testUserAgentEquality(): void
    {
        $knownUserAgent = UserAgent::fromNullable('Browser/1');
        $unknownUserAgent = UserAgent::fromNullable(null);

        self::assertTrue($knownUserAgent->equals(UserAgent::fromNullable('Browser/1')));
        self::assertFalse($knownUserAgent->equals(UserAgent::fromNullable('Browser/2')));
        self::assertFalse($knownUserAgent->equals($unknownUserAgent));
        self::assertTrue($unknownUserAgent->equals(UserAgent::fromNullable('')));
        self::assertFalse($unknownUserAgent->equals($knownUserAgent));
    }

    public function testSessionDeviceFromRequestAndUnknown(): void
    {
        $device = SessionDevice::fromRequest(ip: '203.0.113.7', userAgent: 'Browser/1');

        self::assertInstanceOf(KnownIp::class, $device->ip);
        self::assertSame('203.0.113.7', $device->ip->toNullableString());
        self::assertInstanceOf(KnownUserAgent::class, $device->userAgent);
        self::assertSame('Browser/1', $device->userAgent->toNullableString());

        $unknownDevice = SessionDevice::unknown();

        self::assertInstanceOf(UnknownIp::class, $unknownDevice->ip);
        self::assertInstanceOf(UnknownUserAgent::class, $unknownDevice->userAgent);
        self::assertTrue($unknownDevice->equals(SessionDevice::fromRequest(ip: null, userAgent: null)));
    }

    public function testSessionDeviceEquality(): void
    {
        $device = SessionDevice::fromRequest(ip: '203.0.113.7', userAgent: 'Browser/1');

        self::assertTrue($device->equals(SessionDevice::fromRequest(ip: '203.0.113.7', userAgent: 'Browser/1')));
        self::assertFalse($device->equals(SessionDevice::fromRequest(ip: '198.51.100.1', userAgent: 'Browser/1')));
        self::assertFalse($device->equals(SessionDevice::fromRequest(ip: '203.0.113.7', userAgent: 'Browser/2')));
    }
}
