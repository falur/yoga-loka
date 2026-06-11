<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Core\CoreInterface;

/**
 * Имитирует sync-Job, который сам переводит своё же outbox-событие в финальный статус
 * (handled) и затем падает. interceptor должен увидеть уже-финальное событие в
 * recordJobFailure и не перезаписать его статус ошибкой.
 */
final class MarkFinalThenThrowQueueStatusCore implements CoreInterface
{
    public function __construct(
        private readonly StoredOutboxEvent $storedOutboxEvent,
        private readonly EntityManagerInterface $entityManager,
        private readonly \Throwable $exception,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     */
    #[\Override]
    public function callAction(string $controller, string $action, array $parameters = []): mixed
    {
        $this->storedOutboxEvent->markHandled(new \DateTimeImmutable());
        $this->entityManager->persist($this->storedOutboxEvent);
        $this->entityManager->run();

        throw $this->exception;
    }
}
