<?php

declare(strict_types=1);

namespace Tests;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Spiral\Storage\StorageInterface;
use Tests\Storage\FakeStorage;

/**
 * Базовый класс для обычных DB-тестов.
 *
 * В `setUp()` открывает транзакцию на `DatabaseInterface`, в `tearDown()`
 * откатывает её, поэтому данные не утекают между тестами без ручной очистки
 * таблиц. ORM heap чистится до и после теста, а storage по умолчанию заменён
 * fake-реализацией, чтобы DB-тест не ходил в MinIO.
 *
 * Тесты, которым нужен реальный commit, relay, queue status, console flow или
 * проверка транзакционного поведения, наследуются от
 * `Tests\NonTransactionalDatabaseTestCase`.
 */
abstract class DatabaseTestCase extends TestCase
{
    private DatabaseInterface|null $transactionalDatabase = null;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOrmHeap();

        if ($this->useFakeStorage()) {
            $this->getContainer()->bindSingleton(
                StorageInterface::class,
                new FakeStorage(TestRuntime::storageDirectory($this->rootDirectory())),
            );
        }

        if ($this->useDatabaseTransaction()) {
            $database = $this->getContainer()->get(DatabaseInterface::class);
            $database->begin();
            $this->transactionalDatabase = $database;
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        try {
            if ($this->transactionalDatabase !== null) {
                $this->transactionalDatabase->rollback();
                $this->transactionalDatabase = null;
            }

            $this->cleanOrmHeap();
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Обычный DB-тест оборачивается в транзакцию с rollback.
     */
    protected function useDatabaseTransaction(): bool
    {
        return true;
    }

    /**
     * Обычный DB-тест получает fake storage вместо MinIO.
     */
    protected function useFakeStorage(): bool
    {
        return true;
    }

    protected function cleanOrmHeap(): void
    {
        $container = $this->getContainer();

        if ($container->has(EntityManagerInterface::class)) {
            $container->get(EntityManagerInterface::class)->clean();
        }

        if ($container->has(ORMInterface::class)) {
            $container->get(ORMInterface::class)->getHeap()->clean();
        }
    }
}
