<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use Spiral\Core\CoreInterface;

/**
 * Имитирует sync-Job, который сам переводит своё же outbox-событие в финальный статус
 * (handled) и затем падает. interceptor должен увидеть уже-финальное событие в
 * recordJobFailure и не перезаписать его статус ошибкой.
 *
 * Мутация идёт через собственный findById() (как боевой sync-worker), а не через объект,
 * которым уже владеет тест: StoredOutboxEvent — чистая доменная сущность без Cycle-разметки,
 * Mapper отдаёт на каждый findById() новый объект, поэтому раздельный fetch — единственный
 * способ реально проверить, что interceptor увидел изменение через БД, а не через общий PHP-
 * объект (по образцу MarkHandledDuringPushQueue).
 */
final class MarkFinalThenThrowQueueStatusCore implements CoreInterface
{
    public function __construct(
        private readonly StoredOutboxEventRepository $storedOutboxEventRepository,
        private readonly OutboxEventId $outboxEventId,
        private readonly \Throwable $exception,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     */
    #[\Override]
    public function callAction(string $controller, string $action, array $parameters = []): mixed
    {
        $storedOutboxEvent = $this->storedOutboxEventRepository->findById($this->outboxEventId)
            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
        $storedOutboxEvent->markHandled(new \DateTimeImmutable());
        $this->storedOutboxEventRepository->save($storedOutboxEvent);

        throw $this->exception;
    }
}
