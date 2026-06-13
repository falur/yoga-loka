<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Enum;

use App\Shared\Domain\Enum\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function testHasSupportedCases(): void
    {
        self::assertSame(['ru', 'en'], \array_map(
            static fn(Locale $locale): string => $locale->value,
            Locale::cases(),
        ));
    }
}
