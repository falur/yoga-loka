<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Spiral;

use App\Modules\Media\Public\Event\MediaDeletedEvent;
use App\Modules\Posts\Infrastructure\Spiral\Job\DetachDeletedMediaJob;
use App\Shared\Infrastructure\Spiral\Queue\QueueName;
use Tests\Support\Outbox\AssertsOutboxRoutes;
use Tests\TestCase;

/**
 * Маршрут чужого события объявляет модуль-потребитель: снятие вложений — забота Posts, поэтому
 * пара «MediaDeletedEvent -> DetachDeletedMediaJob» лежит в его bootloader.
 */
final class PostsOutboxRoutesTest extends TestCase
{
    use AssertsOutboxRoutes;

    public function testMediaDeletedGoesToMediaQueueWithTwoRetries(): void
    {
        $this->assertOutboxRoute(
            eventClass: MediaDeletedEvent::class,
            jobClass: DetachDeletedMediaJob::class,
            queueName: QueueName::Media,
            retryDelaysSeconds: [60, 300],
            deliveryTimeoutSeconds: 600,
        );
    }
}
