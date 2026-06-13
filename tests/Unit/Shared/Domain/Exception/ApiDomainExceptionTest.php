<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use GianTiaga\SpiralApiErrors\Exception\TranslatableException;
use PHPUnit\Framework\TestCase;

final class ApiDomainExceptionTest extends TestCase
{
    public function testValidationExceptionHas422CodeAndCarriesKeyWithParameters(): void
    {
        $exception = new ValidationException(
            translationKey: 'app.media.unsupported_file_type',
            translationParameters: ['type' => 'audio/mpeg'],
        );

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertInstanceOf(TranslatableException::class, $exception);
        self::assertSame(422, $exception->getCode());
        self::assertSame('app.media.unsupported_file_type', $exception->translationKey());
        self::assertSame('app.media.unsupported_file_type', $exception->getMessage());
        self::assertSame('media', $exception->translationDomain());
        self::assertSame(['type' => 'audio/mpeg'], $exception->translationParameters());
    }

    public function testAuthenticationExceptionHas401CodeAndDefaultsToEmptyParameters(): void
    {
        $exception = new AuthenticationException('app.auth.required');

        self::assertInstanceOf(TranslatableException::class, $exception);
        self::assertSame(401, $exception->getCode());
        self::assertSame('app.auth.required', $exception->translationKey());
        self::assertSame('auth', $exception->translationDomain());
        self::assertSame([], $exception->translationParameters());
    }

    public function testForbiddenExceptionHas403Code(): void
    {
        $exception = new ForbiddenException('app.media.access_denied');

        self::assertSame(403, $exception->getCode());
        self::assertSame('app.media.access_denied', $exception->translationKey());
        self::assertSame('media', $exception->translationDomain());
        self::assertSame([], $exception->translationParameters());
    }

    public function testNotFoundExceptionHas404Code(): void
    {
        $exception = new NotFoundException('app.media.not_found');

        self::assertSame(404, $exception->getCode());
        self::assertSame('app.media.not_found', $exception->translationKey());
        self::assertSame('media', $exception->translationDomain());
        self::assertSame([], $exception->translationParameters());
    }

    public function testTranslationDomainFallsBackToMessagesForKeyWithoutModuleSegment(): void
    {
        $exception = new NotFoundException('plainkey');

        self::assertSame('plainkey', $exception->translationKey());
        self::assertSame('messages', $exception->translationDomain());
    }

    public function testInvalidDomainValueExceptionHas500Code(): void
    {
        $exception = new InvalidDomainValueException(message: 'Некорректное доменное значение');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame(500, $exception->getCode());
    }
}
