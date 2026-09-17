<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;

final readonly class ThrowingQueue implements QueueInterface
{
    public function __construct(
        private string $exceptionMessage,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    #[\Override]
    public function push(string $name, array $payload = [], OptionsInterface|null $options = null): string
    {
        throw new \RuntimeException($this->exceptionMessage);
    }
}
