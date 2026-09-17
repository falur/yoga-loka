<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Cycle;

use Psr\Log\AbstractLogger;

/**
 * Логгер-спай для подсчёта реально выполненных SQL-запросов в тесте: Cycle\Database\Driver\Driver
 * логирует каждый выполненный запрос через info($queryString, $context) (см.
 * vendor/cycle/database/src/Driver/Driver.php). Подключается на Driver конкретной БД перед вызовом
 * репозитория и снимается сразу после — используется там, где важно доказать отсутствие лишнего
 * запроса к таблице, а не только итоговое состояние (которое совпало бы что при верной, что при
 * ошибочной реализации, если в БД для этой таблицы нет строк).
 */
final class RecordingQueryLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $queries = [];

    #[\Override]
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if ($level !== 'info') {
            return;
        }

        $this->queries[] = (string) $message;
    }

    /**
     * @return list<string>
     */
    public function queriesTouching(string $tableName): array
    {
        return \array_values(\array_filter(
            $this->queries,
            static fn(string $query): bool => \str_contains($query, $tableName),
        ));
    }
}
