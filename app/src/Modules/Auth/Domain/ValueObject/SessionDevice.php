<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

/**
 * Устройство сессии: IP и User-Agent клиента. Захватывается при выдаче пары токенов и при
 * ротации. Примитивы границы запроса превращаются в типобезопасные VO через fromRequest;
 * unknown() — для контекстов без запроса (vendor-граница TokenStorageInterface::create).
 */
final readonly class SessionDevice
{
    private function __construct(
        public Ip $ip,
        public UserAgent $userAgent,
    ) {}

    public static function fromRequest(string|null $ip, string|null $userAgent): self
    {
        return new self(
            ip: Ip::fromNullable($ip),
            userAgent: UserAgent::fromNullable($userAgent),
        );
    }

    public static function unknown(): self
    {
        return new self(
            ip: new UnknownIp(),
            userAgent: new UnknownUserAgent(),
        );
    }

    public function equals(self $other): bool
    {
        return $this->ip->equals($other->ip) && $this->userAgent->equals($other->userAgent);
    }
}
