<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Job;

use App\Modules\Media\Public\Event\MediaDeletedEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use App\Modules\Posts\Application\Command\DetachDeletedMedia\DetachDeletedMediaCommand;
use App\Modules\Posts\Application\Command\DetachDeletedMedia\DetachDeletedMediaHandler;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job-потребитель MediaDeletedEvent модуля Media. Грузит событие из outbox и
 * запускает DetachDeletedMediaCommand — входной адаптер очереди без бизнес-правил, идемпотентность
 * обеспечивает сценарий (см. докблок DetachDeletedMediaHandler).
 */
final class DetachDeletedMediaJob extends JobHandler
{
    public function invoke(
        OutboxEnvelopeDto $payload,
        string $id,
        IntegrationEventLoaderContract $integrationEventLoader,
        CommandBusInterface $commandBus,
        DetachDeletedMediaHandler $detachDeletedMediaHandler,
    ): void {
        $mediaDeleted = $integrationEventLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedEventClass: MediaDeletedEvent::class,
        );

        $commandBus->dispatch(
            command: new DetachDeletedMediaCommand(mediaId: $mediaDeleted->mediaId),
            handler: $detachDeletedMediaHandler->handle(...),
        );
    }
}
