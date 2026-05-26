<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Tests\Support;

use Psr\Log\AbstractLogger;

final class TestLogger extends AbstractLogger
{
    /** @var list<LogRecord> */
    private array $records = [];

    /** @param array<array-key, object|string|int|float|bool|null> $context */
    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!\is_string($level) && !$level instanceof \Stringable) {
            throw new \InvalidArgumentException(message: 'Некорректный уровень лога.');
        }

        $this->records[] = new LogRecord(
            level: (string) $level,
            message: (string) $message,
        );
    }

    /**
     * @return list<LogRecord>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @return list<string>
     */
    public function messagesForLevel(string $level): array
    {
        $messages = [];

        foreach ($this->records as $record) {
            if ($record->level !== $level) {
                continue;
            }

            $messages[] = $record->message;
        }

        return $messages;
    }
}
