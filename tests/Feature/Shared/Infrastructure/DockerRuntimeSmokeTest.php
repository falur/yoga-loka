<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Infrastructure;

use Spiral\Storage\StorageInterface;
use Tests\RealStorageTestCase;

final class DockerRuntimeSmokeTest extends RealStorageTestCase
{
    public function testApplicationBootsWithDockerTestEnvironment(): void
    {
        self::assertSame('pgsql', \env('DB_CONNECTION'));
        self::assertSame($this->expectedDatabase(), \env('DB_DATABASE'));
        self::assertSame('local', \env('CACHE_STORAGE'));
    }

    public function testBootstrapAppliesWorkerDatabaseAndBucket(): void
    {
        // tests/bootstrap.php по TEST_TOKEN выставляет worker-значения до загрузки
        // приложения. app/config/database.php и storage.php читают их через env(),
        // поэтому worker-значение должно победить базовое значение из окружения контейнера.
        self::assertSame($this->expectedDatabase(), \env('DB_DATABASE'));
        self::assertSame($this->expectedBucket(), \env('S3_BUCKET'));
        self::assertSame($this->expectedBucket(), \env('S3_TEST_BUCKET'));
    }

    public function testStorageCanUseTestBucket(): void
    {
        $bucket = $this->getContainer()
            ->get(StorageInterface::class)
            ->bucket('s3-test');

        $path = \sprintf('smoke/%s.txt', \bin2hex(\random_bytes(8)));
        $this->trackStorageObject('s3-test', $path);

        $bucket->write($path, 'ok');

        self::assertTrue($bucket->exists($path));
        self::assertSame('ok', $bucket->getContents($path));
    }

    private function token(): string
    {
        $token = \getenv('TEST_TOKEN');

        return $token === false ? '' : $token;
    }

    private function expectedDatabase(): string
    {
        $token = $this->token();

        return $token === '' ? 'yoga_loka_test' : 'yoga_loka_test_' . $token;
    }

    private function expectedBucket(): string
    {
        $token = $this->token();

        return $token === '' ? 'yoga-loka-test' : 'yoga-loka-test-' . $token;
    }
}
