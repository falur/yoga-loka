<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Locale;

use App\Shared\Domain\Locale\LocaleResolver;
use PHPUnit\Framework\TestCase;

final class LocaleResolverTest extends TestCase
{
    public function testReturnsSupportedLocaleAsIs(): void
    {
        self::assertSame('ru', $this->resolver()->resolve('ru'));
        self::assertSame('en', $this->resolver()->resolve('en'));
    }

    public function testFallsBackToDefaultForUnsupportedLocale(): void
    {
        self::assertSame('ru', $this->resolver()->resolve('fr'));
    }

    public function testFallsBackToDefaultForEmptyLocale(): void
    {
        self::assertSame('ru', $this->resolver()->resolve(''));
    }

    private function resolver(): LocaleResolver
    {
        return new LocaleResolver(supported: ['ru', 'en'], default: 'ru');
    }
}
