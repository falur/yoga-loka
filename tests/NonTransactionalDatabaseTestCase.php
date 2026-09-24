<?php

declare(strict_types=1);

namespace Tests;

/**
 * DB-тесты, где общий транзакционный rollback ломает проверку: реальный commit,
 * проход outbox relay, статусы доставок, console flow или явные транзакционные сценарии.
 *
 * Такие тесты чистят свои данные сами (явной очисткой таблиц), но всё ещё
 * получают fake storage по умолчанию, если storage не является предметом
 * проверки.
 */
abstract class NonTransactionalDatabaseTestCase extends DatabaseTestCase
{
    #[\Override]
    protected function useDatabaseTransaction(): bool
    {
        return false;
    }
}
