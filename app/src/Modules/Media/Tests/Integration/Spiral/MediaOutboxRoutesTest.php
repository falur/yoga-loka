<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Infrastructure\Spiral\Job\ProcessMediaJob;
use App\Modules\Media\Public\Event\MediaUploadedEvent;
use App\Shared\Infrastructure\Spiral\Queue\QueueName;
use Tests\Support\Outbox\AssertsOutboxRoutes;
use Tests\TestCase;

/**
 * Маршрут своего события объявляет MediaBootloader: обработку загруженного медиа выполняет сам
 * модуль, поэтому и маршрут лежит в нём.
 */
final class MediaOutboxRoutesTest extends TestCase
{
    use AssertsOutboxRoutes;

    public function testMediaUploadedGoesToMediaQueueWithThreeRetriesAndLongTimeout(): void
    {
        $this->assertOutboxRoute(
            eventClass: MediaUploadedEvent::class,
            jobClass: ProcessMediaJob::class,
            queueName: QueueName::Media,
            retryDelaysSeconds: [60, 300, 900],
            deliveryTimeoutSeconds: 7200,
        );
    }
}
