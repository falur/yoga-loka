<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\System\Infrastructure\Spiral\Temporal;

use App\Modules\System\Infrastructure\Spiral\Temporal\Ping;
use PHPUnit\Framework\TestCase;

final class PingTest extends TestCase
{
    public function testPingWorkflowReturnsPong(): void
    {
        self::assertSame('pong', (new Ping())->handle());
    }
}
