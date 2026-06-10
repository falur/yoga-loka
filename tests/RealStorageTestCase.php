<?php

declare(strict_types=1);

namespace Tests;

use Spiral\Storage\StorageInterface;

/**
 * DB-тесты, где реальный MinIO/S3 является предметом проверки.
 *
 * Наследуется от `Tests\NonTransactionalDatabaseTestCase`, явно отключает fake
 * storage и чистит созданные объекты своего bucket-а в `tearDown()`. Созданные
 * объекты регистрируются через `trackStorageObject()`.
 */
abstract class RealStorageTestCase extends NonTransactionalDatabaseTestCase
{
    /**
     * @var list<array{bucket: string, pathname: string}>
     */
    private array $createdStorageObjects = [];

    #[\Override]
    protected function useFakeStorage(): bool
    {
        return false;
    }

    protected function trackStorageObject(string $bucket, string $pathname): void
    {
        $this->createdStorageObjects[] = ['bucket' => $bucket, 'pathname' => $pathname];
    }

    #[\Override]
    protected function tearDown(): void
    {
        try {
            $this->cleanCreatedStorageObjects();
        } finally {
            parent::tearDown();
        }
    }

    private function cleanCreatedStorageObjects(): void
    {
        if ($this->createdStorageObjects === []) {
            return;
        }

        $storage = $this->getContainer()->get(StorageInterface::class);

        foreach ($this->createdStorageObjects as $createdStorageObject) {
            $storage->bucket($createdStorageObject['bucket'])->delete($createdStorageObject['pathname']);
        }

        $this->createdStorageObjects = [];
    }
}
