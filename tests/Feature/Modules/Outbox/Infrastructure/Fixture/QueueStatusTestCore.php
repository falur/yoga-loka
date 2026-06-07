<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use Spiral\Core\CoreInterface;

final class QueueStatusTestCore implements CoreInterface
{
    public bool $called = false;

    public function __construct(
        private readonly \Throwable|null $exception = null,
        private readonly \Closure|null $afterCall = null,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     */
    #[\Override]
    public function callAction(string $controller, string $action, array $parameters = []): mixed
    {
        $this->called = true;

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        if ($this->afterCall instanceof \Closure) {
            ($this->afterCall)();
        }

        return null;
    }
}
