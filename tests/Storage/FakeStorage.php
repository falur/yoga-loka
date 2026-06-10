<?php

declare(strict_types=1);

namespace Tests\Storage;

use League\Flysystem\Local\LocalFilesystemAdapter;
use Spiral\Storage\Bucket;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exception\InvalidArgumentException;
use Spiral\Storage\FileInterface;
use Spiral\Storage\Storage;
use Spiral\Storage\StorageInterface;

/**
 * Fake storage для тестов: подменяет S3/MinIO локальной файловой системой
 * внутри runtime-каталога текущего тестового процесса.
 *
 * `Tests\DatabaseTestCase` ставит его по умолчанию, чтобы DB-тесты не ходили в
 * MinIO, когда storage не является предметом проверки. Реализация делегирует в
 * обычный `Spiral\Storage\Storage` поверх local-адаптеров, поэтому поддерживает
 * все методы контракта (`write`, `exists`, `getContents`, `delete` и прочие)
 * без обращения к сети.
 */
final class FakeStorage implements StorageInterface
{
    /**
     * Базовый набор bucket-ов тестового окружения. Остальные имена создаются
     * лениво при первом обращении.
     */
    private const BUCKETS = ['default', 's3', 's3-test', 'media-upload', 'media-private', 'media-public'];

    private readonly Storage $storage;

    public function __construct(
        private readonly string $storageDirectory,
    ) {
        $this->storage = new Storage('default');

        foreach (self::BUCKETS as $bucket) {
            $this->storage->add($bucket, $this->makeBucket($bucket));
        }
    }

    public function bucket(string|null $name = null): BucketInterface
    {
        $name ??= 'default';

        try {
            return $this->storage->bucket($name);
        } catch (InvalidArgumentException) {
            $this->storage->add($name, $this->makeBucket($name));

            return $this->storage->bucket($name);
        }
    }

    public function file(string|\Stringable $id): FileInterface
    {
        return $this->storage->file($id);
    }

    public function withDefault(string $name): StorageInterface
    {
        return $this->storage->withDefault($name);
    }

    public function getIterator(): \Traversable
    {
        return $this->storage->getIterator();
    }

    public function count(): int
    {
        return $this->storage->count();
    }

    public function getContents(string|\Stringable $id): string
    {
        return $this->storage->getContents($id);
    }

    public function getStream(string|\Stringable $id)
    {
        return $this->storage->getStream($id);
    }

    public function exists(string|\Stringable $id): bool
    {
        return $this->storage->exists($id);
    }

    public function getLastModified(string|\Stringable $id): int
    {
        return $this->storage->getLastModified($id);
    }

    public function getSize(string|\Stringable $id): int
    {
        return $this->storage->getSize($id);
    }

    public function getMimeType(string|\Stringable $id): string
    {
        return $this->storage->getMimeType($id);
    }

    public function getVisibility(string|\Stringable $id): string
    {
        return $this->storage->getVisibility($id);
    }

    public function create(string|\Stringable $id, array $config = []): FileInterface
    {
        return $this->storage->create($id, $config);
    }

    public function write(string|\Stringable $id, mixed $content, array $config = []): FileInterface
    {
        return $this->storage->write($id, $content, $config);
    }

    public function setVisibility(string|\Stringable $id, string $visibility): FileInterface
    {
        return $this->storage->setVisibility($id, $visibility);
    }

    public function copy(string|\Stringable $source, string|\Stringable $destination, array $config = []): FileInterface
    {
        return $this->storage->copy($source, $destination, $config);
    }

    public function move(string|\Stringable $source, string|\Stringable $destination, array $config = []): FileInterface
    {
        return $this->storage->move($source, $destination, $config);
    }

    public function delete(string|\Stringable $id, bool $clean = false): void
    {
        $this->storage->delete($id, $clean);
    }

    private function makeBucket(string $name): BucketInterface
    {
        return Bucket::fromAdapter(
            new LocalFilesystemAdapter($this->storageDirectory . '/' . $name),
            $name,
        );
    }
}
