<?php

declare(strict_types=1);

namespace Tools\Cqrs\Tests\Support;

final class ExecutionLog
{
    /** @var list<string> */
    private array $events = [];

    public function add(string $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }
}
