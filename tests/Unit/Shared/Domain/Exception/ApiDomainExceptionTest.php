<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ApiDomainExceptionTest extends TestCase
{
    public function testValidationExceptionHas422Code(): void
    {
        $exception = new ValidationException(message: 'Некорректное значение');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame(422, $exception->getCode());
    }

    public function testAuthenticationExceptionHas401Code(): void
    {
        $exception = new AuthenticationException(message: 'Нужна аутентификация');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame(401, $exception->getCode());
    }

    public function testForbiddenExceptionHas403Code(): void
    {
        $exception = new ForbiddenException(message: 'Доступ запрещён');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame(403, $exception->getCode());
    }

    public function testNotFoundExceptionHas404Code(): void
    {
        $exception = new NotFoundException(message: 'Не найдено');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame(404, $exception->getCode());
    }

    public function testInvalidDomainValueExceptionHas500Code(): void
    {
        $exception = new InvalidDomainValueException(message: 'Некорректное доменное значение');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame(500, $exception->getCode());
    }
}
