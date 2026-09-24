<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Job;

use App\Modules\Media\Public\Event\MediaDeletedEvent;
use App\Modules\Posts\Application\Command\DetachDeletedMedia\DetachDeletedMediaCommand;
use App\Modules\Posts\Application\Command\DetachDeletedMedia\DetachDeletedMediaHandler;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job-потребитель MediaDeletedEvent модуля Media. Грузит событие по
 * идентификатору доставки и запускает DetachDeletedMediaCommand — входной адаптер очереди без
 * бизнес-правил, идемпотентность обеспечивает сценарий (см. докблок DetachDeletedMediaHandler).
 */
final class DetachDeletedMediaJob extends JobHandler
{
    public function invoke(
        string $outboxDeliveryId,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        DetachDeletedMediaHandler $detachDeletedMediaHandler,
    ): void {
        $mediaDeleted = $outboxMessageLoader->load(
            outboxDeliveryId: $outboxDeliveryId,
            expectedMessageClass: MediaDeletedEvent::class,
        );

        $commandBus->dispatch(
            command: new DetachDeletedMediaCommand(mediaId: $mediaDeleted->mediaId),
            handler: $detachDeletedMediaHandler->handle(...),
        );
    }
}
