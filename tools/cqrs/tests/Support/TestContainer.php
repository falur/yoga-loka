<?php

declare(strict_types=1);

namespace Tools\Cqrs\Tests\Support;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final readonly class TestContainer implements ContainerInterface
{
    /**
     * @param array<class-string, object> $services
     */
    public function __construct(
        private array $services,
    ) {}

    #[\Override]
    public function get(string $id): object
    {
        if (!$this->has(id: $id)) {
            throw new TestContainerException(message: \sprintf('Сервис не найден: %s', $id));
        }

        return $this->services[$id];
    }

    #[\Override]
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

final class TestContainerException extends \RuntimeException implements NotFoundExceptionInterface {}
