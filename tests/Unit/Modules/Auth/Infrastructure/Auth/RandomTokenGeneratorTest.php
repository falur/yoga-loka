<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Auth;

use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use PHPUnit\Framework\TestCase;

final class RandomTokenGeneratorTest extends TestCase
{
    public function testGeneratesUniqueUrlSafeTokens(): void
    {
        $generator = new RandomTokenGenerator();

        $first = $generator->generate();
        $second = $generator->generate();

        self::assertNotSame($first, $second);
        self::assertSame(43, \strlen($first));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $first);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $second);
    }
}
