<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Bootloader;

use App\Shared\Domain\Locale\LocaleResolver;
use App\Shared\Infrastructure\Spiral\Configuration\Locale\LocaleConfig;
use Tests\TestCase;

final class AppBootloaderTest extends TestCase
{
    public function testLocaleResolverIsBuiltFromConfig(): void
    {
        $localeConfig = $this->getContainer()->get(LocaleConfig::class);
        $localeResolver = $this->getContainer()->get(LocaleResolver::class);

        self::assertInstanceOf(LocaleResolver::class, $localeResolver);
        // Резолвер собран именно из LocaleConfig фабрикой бутлоадера: неподдерживаемая локаль сводится
        // к фактическому значению по умолчанию из конфига (а не к произвольной из supported)...
        self::assertSame($localeConfig->default, $localeResolver->resolve('zz'));
        // ...а каждая поддерживаемая локаль возвращается как есть.
        foreach ($localeConfig->supported as $supported) {
            self::assertSame($supported, $localeResolver->resolve($supported));
        }
    }
}
