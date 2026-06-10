<?php

declare(strict_types=1);

namespace Tests\Kernel;

use Tests\TestCase;

class DemoTest extends TestCase
{
    public function testDemo(): void
    {
        $expected = true;
        $actual = false;

        self::assertTrue($expected);
        self::assertFalse($actual);
    }
}
