<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Logging;

final readonly class DebugLogger
{
    /**
     * @param null|callable(string): void $writer
     */
    public function __construct(private bool $enabled, private mixed $writer = null) {}
    public function debug(string $message): void
    {
        if (!$this->enabled) {
            return;
        }
        if (\is_callable($this->writer)) {
            ($this->writer)($message);
        }
    }
}
