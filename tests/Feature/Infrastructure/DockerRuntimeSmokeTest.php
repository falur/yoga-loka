<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Spiral\Storage\StorageInterface;
use Tests\TestCase;

final class DockerRuntimeSmokeTest extends TestCase
{
    public function testApplicationBootsWithDockerTestEnvironment(): void
    {
        self::assertSame('pgsql', \env('DB_CONNECTION'));
        self::assertSame('yoga_loka_test', \env('DB_DATABASE'));
        self::assertSame('local', \env('CACHE_STORAGE'));
    }

    public function testStorageCanUseTestBucket(): void
    {
        $bucket = $this->getContainer()
            ->get(StorageInterface::class)
            ->bucket('s3-test');

        $path = \sprintf('smoke/%s.txt', \bin2hex(\random_bytes(8)));

        $bucket->write($path, 'ok');

        self::assertTrue($bucket->exists($path));
        self::assertSame('ok', $bucket->getContents($path));

        $bucket->delete($path);
    }
}
