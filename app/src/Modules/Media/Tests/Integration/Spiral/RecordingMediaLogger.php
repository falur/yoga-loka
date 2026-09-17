<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use Psr\Log\AbstractLogger;

/**
 * Логгер-спай для проверки логов Media-сценариев: уровня (транзиентный сбой -> WARNING,
 * постоянный -> ERROR) и контекста (например, applied presignedTtlSeconds в запросе загрузки).
 */
final class RecordingMediaLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasLevel(string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                return true;
            }
        }

        return false;
    }

    /**
     * Контекст первой записи с указанным сообщением; пустой массив, если записи нет.
     *
     * @return array<string, mixed>
     */
    public function contextFor(string $message): array
    {
        foreach ($this->records as $record) {
            if ($record['message'] === $message) {
                return $record['context'];
            }
        }

        return [];
    }
}
