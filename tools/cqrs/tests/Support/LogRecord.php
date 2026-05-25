<?php

declare(strict_types=1);

namespace Tools\Cqrs\Tests\Support;

final readonly class LogRecord
{
    public function __construct(
        public string $level,
        public string $message,
    ) {}
}
