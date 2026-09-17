<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use Psr\Log\AbstractLogger;

final class RecordingOutboxLogger extends AbstractLogger
{
    /**
     * @var list<array<string, mixed>>
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

    public function hasRecord(string $level, string $messageSubstring): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && \str_contains((string) $record['message'], $messageSubstring)) {
                return true;
            }
        }

        return false;
    }
}
